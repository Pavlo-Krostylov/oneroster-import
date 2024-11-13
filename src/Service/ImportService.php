<?php

namespace oat\OneRoster\Service;

use Doctrine\Common\Collections\ArrayCollection;
use oat\OneRoster\File\FileHandler;
use oat\OneRoster\Import\ImporterFactory;

class ImportService
{
    /** @var array Default CSV controls */
    protected $options = [
        'version' => 'v1.1',
        'csvControl' => [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
        ]
    ];

    /** @var FileHandler */
    private $fileHandler;

    /** @var string */
    private $pathToFolder;

    /**
     * ImportService constructor.
     * @param $fileHandler
     */
    public function __construct(FileHandler $fileHandler)
    {
        $this->fileHandler = $fileHandler;
    }

    /**
     * @param $file
     * @param $type
     * @param array $options
     * @return ArrayCollection
     * @throws \Exception
     */
    public function import($file, $type, $options = [])
    {
        $this->options = array_merge($this->options, $options);
        $this->validateOptions();

        $importer = (new ImporterFactory($this->options['version'], $this->fileHandler))->build($type);
        $fileResource = $this->fileHandler->open($file);

        $lines  = [];
        $header = [] ;

        list($delimiter, $enclosure, $escape) = array_values($this->options['csvControl']);
        $index = 0;
        while (is_array($line = $this->fileHandler->readCsvLine($fileResource, 0, $delimiter, $enclosure, $escape))) {
            $index++;
            $dataLine = array_map('trim', $line);
            if ($index === 1) {
                $header = $dataLine;
                continue;
            }
            $lines[] = $dataLine;
        }

        $result = $importer->import($header, $lines);

        return $result;
    }

    /**
     * @param string $pathToFolder
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function importMultiple(string $pathToFolder, array $options = []): array
    {
        $this->options = array_merge($this->options, $options);
        $this->validateOptions();
        $results = [];

        $this->setPathToFolder($pathToFolder);
        $availableTypes = $this->detectAvailableTypes();

        foreach ($availableTypes as $availableType) {
            $results[$availableType] = $this->import($this->pathToFolder . $availableType . '.csv', $availableType, $this->options);
        }

        return $results;
    }

    /**
     * @return string
     */
    public function getPathToFolder(): string
    {
        return $this->pathToFolder;
    }

    /**
     * @param string $pathToFolder
     */
    public function setPathToFolder(string $pathToFolder): void
    {
        $this->pathToFolder = $this->normalizePath($pathToFolder);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function detectAvailableTypes(): array
    {
        $types = [];
        $result = $this->import($this->pathToFolder . 'manifest.csv', 'manifest', $this->options);

        foreach ($result as $row) {
            $property = $row['propertyName'];
            $value    = $row['value'];

            if (strpos($property, 'file.') !== false
                && $value !== 'absent')
            {
                $parts = explode('.', $property);
                $types[] = end($parts);
            }
        }

        return $types;
    }

    /**
     * @throws \Exception
     */
    private function validateOptions(): void
    {
        if (!isset($this->options['version'])) {
            throw new \Exception('Version should be specified as option');
        }

        if (!isset($this->options['csvControl'])) {
            throw new \Exception('csvControl should be specified as option');
        }
    }

    /**
     * @param string $path
     * @return string
     */
    private function normalizePath(string $path): string
    {
        $last_char = substr($path, -1);
        if($last_char !== '\\' && $last_char !== '/') {
            $path .= '/';
        }
        return $path;
    }
}
