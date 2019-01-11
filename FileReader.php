<?php
/**
 * class CSVReader
 *
 * reads csv report
 */
class CSVReader
{
    /**
     * @var resource
     */
    protected $fileHandler;

    /**
     * @var string
     */
    protected $delimiter;

    /**
     * @var string
     */
    protected $enclosure;

    /**
     * @param string $filename
     * @param string $delimiter
     * @param string $enclosure
     * @throws Exception
     */
    public function __construct(string $filename, string $delimiter = ',', string $enclosure = '"')
    {
        if (!is_readable($filename)) {
            throw new Exception('File is not readable');
        }
        $this->fileHandler = fopen($filename, 'r');
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
    }

    /**
     * read single row
     *
     * @return array|null|false
     */
    public function readRow()
    {
        return fgetcsv($this->fileHandler, 0, $this->delimiter, $this->enclosure);
    }

    /**
     * get file headings
     *
     * @return array
     * @throws Exception
     */
    public function getHeadings()
    {
        rewind($this->fileHandler);
        $row = $this->readRow();
        if ($row === false) {
            throw new Exception('File is empty');
        } elseif (is_null($row)) {
            throw new Exception('File can not be processed');
        }
        return $row;
    }
}

