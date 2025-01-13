<?php

namespace oat\OneRoster\Import;

use Doctrine\Common\Collections\ArrayCollection;
use oat\OneRoster\Schema\NotUniqueEntityException;
use oat\OneRoster\Schema\Validator;

class Importer implements ImporterInterface
{
    /** @var Validator */
    private $schemaValidator;

    /**
     * Importer constructor.
     * @param Validator $schemaValidator
     */
    public function __construct(Validator $schemaValidator)
    {
        $this->schemaValidator = $schemaValidator;
    }

    public function import(array $header, array $data): ArrayCollection
    {
        $result = new ArrayCollection();
        $headerSize = count($header);

        foreach ($data as $index => $row){
            $rowWitHeader = array_combine($header, $this->fixRowSize($headerSize, $row));
            $rowWitHeader = $this->schemaValidator->validate($rowWitHeader);

            if (isset($rowWitHeader['sourcedId'])){
                if ($result->containsKey($rowWitHeader['sourcedId'])) {
                    throw new NotUniqueEntityException($rowWitHeader['sourcedId']);
                }

                $result->set($rowWitHeader['sourcedId'], $rowWitHeader);
            } else {
                $result->add($rowWitHeader);
            }
        }

        return $result;
    }

    /**
     * @param int $rowSize
     * @param array $data
     * @return array
     */
    protected function fixRowSize(int $rowSize, array $data): array
    {
        $dataSize = count($data);
        $diff = $rowSize - $dataSize;
        if ($diff > 0) {
            for ($i = 0; $i < $diff; $i++) {
                $data[] = '';
            }
        } elseif ($diff < 0) {
            $data = array_slice($data, 0, $rowSize);
        }
        return $data;
    }
}
