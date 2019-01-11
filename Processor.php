<?php

require_once 'FileReader.php';
require_once 'DbConnector.php';
/**
 * handles all the processing flow
 *
 * class Processor
 */
class Processor
{
    const MERCHANT_ID             = 'mid'; // digits only, up to 18 digits
    const MERCHANT_NAME           = 'dba'; // string, max length - 100
    const BATCH_DATE              = 'batch_date'; // YYYY-MM-DD
    const BATCH_REF_NUM           = 'batch_ref_num'; // digits only, up to 24 digits
    const TRANSACTION_DATE        = 'trans_date'; // YYYY-MM-DD
    const TRANSACTION_TYPE        = 'trans_type'; // string, max length - 20
    const TRANSACTION_CARD_TYPE   = 'trans_card_type'; // string, max length - 2, possible values - VI/MC/AX and so on
    const TRANSACTION_CARD_NUMBER = 'trans_card_num'; // string, max length - 20
    const TRANSACTOIN_AMOUNT      = 'trans_amount'; // amount, negative values are possible

    const BLOCK_LENGTH            = 1000; // nnumber of records to insert

    /**
     * @var array
     */
    protected $mapping;

    /**
     * @var CSVReader
     */
    protected $reader;

    /**
     * @var array
     */
    protected $positions = [];

    /**
     * @var array
     */
    protected $mappingKeys = [];

    /**
     * @var array
     */
    protected $blockMerchants = [];

    /**
     * @var array
     */
    protected $blockBatches = [];

    /**
     * @var array
     */
    protected $blockMerchantIds = [];

    /**
     * @var array
     */
    protected $blockBatchesIds = [];

    /**
     * @var array
     */
    protected $transactionsToInsert = [];

    /**
     * @var string
     */
    protected $importId;

    /**
     * @var DbConnector
     */
    protected $connector;

    /**
     * @param array $mapping
     */
    public function __construct(array $mapping = [])
    {
        $this->mapping = $mapping;
    }

    /**
     * validates that importId is unique
     */
    protected function generateImportId()
    {
        $unique = false;
        while (!$unique) {
            $this->importId = uniqid('', true);
            if (!$this->connector->checkImportIsUnique($this->importId)) {
                continue;
            }
            $unique = true;
        }
    }

    /**
     * @param CSVReader $reader
     * @return static
     */
    public function setReader(CSVReader $reader)
    {
        $this->reader = $reader;
        return $this;
    }

    /**
     * @param DbConnector $connector
     * @return static
     */
    public function setConnector(DbConnector $connector)
    {
        $this->connector = $connector;
        return $this;
    }

    /**
     * run import
     */
    public function process()
    {
        $this->fillPositions();
        $this->generateImportId();
        try {
            $this->insertData();
        } catch (Exception $ex) {
            $this->connector->deleteTransactionsByImport($this->importId);
            echo $ex->getMessage() . "\n";
        }
        $this->reader->closeHandler();
    }

    /**
     * insert transactions
     */
    protected function insertData()
    {
        $insertedTransactions = 0;
        while ($data = $this->getRows()) {
            $this->prepeareBlock($data);
            $this->connector->doBatchInsert(
                'transactions',
                ['batch_id', 'transaction_date', 'type', 'card_type', 'card_number', 'amount', 'import_id'],
                $this->transactionsToInsert
            );
            $insertedTransactions += count($this->transactionsToInsert);
        }
        echo "inserted transactions: $insertedTransactions \n";
    }

    /**
     * get certain number of rows to insert
     *
     * @return array
     */
    protected function getRows()
    {
        $ctr = 0;
        $data = [];
        while ($ctr < self::BLOCK_LENGTH && ($row = $this->reader->readRow())) {
            $dataRow = [];
            foreach ($this->mappingKeys as $key) {
                $dataRow[$key] = $this->getRowValueByKey($key, $row);
            }
            array_push($data, $dataRow);
            $ctr++;
        }
        return $data;
    }

    /**
     * validates headings and map column key to column number
     *
     * @throws Exception
     */
    protected function fillPositions()
    {
        $headings = $this->reader->getHeadings();
        if (count(array_intersect(array_values($this->mapping), $headings)) !== count($this->mapping)) {
            throw new Exception('To few mapped columns');
        }
        $this->mappingKeys = array_keys($this->mapping);
        foreach ($this->mappingKeys as $key) {
            $this->positions[$key] = array_search($this->mapping[$key], $headings);
        }
    }

    /**
     * prepare block for insert
     *
     * @param array $data
     */
    protected function prepeareBlock($data)
    {
        $this->blockMerchants = [];
        $this->blockBatches = [];
        $this->transactionsToInsert = [];
        foreach ($data as $row) {
            $this->blockMerchants[$row[self::MERCHANT_ID]] = [
                'merchant_id' => $row[self::MERCHANT_ID],
                'merchant_name' => $row[self::MERCHANT_NAME]
            ];
            $batchId = join('_', [
                $row[self::MERCHANT_ID],
                $row[self::BATCH_REF_NUM],
                $row[self::BATCH_DATE],
            ]);
            $this->blockBatches[$batchId] = [
                'batch_id' => $batchId,
                'merchant_id' => $row[self::MERCHANT_ID],
                'batch_ref_num' => $row[self::BATCH_REF_NUM],
                'batch_date' => $row[self::BATCH_DATE]
            ];
            $this->transactionsToInsert[] = [
                'batch_id' => $batchId,
                'transaction_date' => $row[self::TRANSACTION_DATE],
                'type' => $row[self::TRANSACTION_TYPE],
                'card_type' => $row[self::TRANSACTION_CARD_TYPE],
                'card_number' => $row[self::TRANSACTION_CARD_NUMBER],
                'amount' => $row[self::TRANSACTOIN_AMOUNT],
                'import_id' => $this->importId,
            ];
        }
        $this->saveMerchants($this->blockMerchants)
            ->fillBlockMerchantIds();
        foreach ($this->blockBatches as $key => $block) {
            $this->blockBatches[$key]['merchant_id'] = $this->blockMerchantIds[$block['merchant_id']];
        }
        $this->saveBatches($this->blockBatches)
            ->fillBlockBatchesIds();
        foreach ($this->transactionsToInsert as $key => $transaction) {
            $this->transactionsToInsert[$key]['batch_id'] = $this->blockBatchesIds[$transaction['batch_id']];
        }
    }

    /**
     * @param array $merchants
     * @return static
     */
    protected function saveMerchants(array $merchants)
    {
        $this->connector->doBatchInsert('merchants', ['ext_merchant_id', 'name'], $merchants);
        return $this;
    }

    /**
     * @param array $batches
     * @return static
     */
    protected function saveBatches(array $batches)
    {
        $this->connector->doBatchInsert(
            'batches',
            ['batch_id', 'merchant_id', 'batch_ref_num', 'batch_date'],
            $batches
        );
        return $this;
    }

    /**
     * map external merchant id to db id
     *
     * @return $this
     */
    protected function fillBlockMerchantIds()
    {
        $this->blockMerchantIds = $this->connector->getMerchantsByExtIds(array_keys($this->blockMerchants));
        return $this;
    }

    /**
     * * map batch id to db id
     *
     * @return $this
     */
    protected function fillBlockBatchesIds()
    {
        $this->blockBatchesIds = $this->connector->getBatchesByExtIds(array_keys($this->blockBatches));
        return $this;
    }

    /**
     * @param string $key
     * @param array $row
     * @return mixed
     */
    protected function getRowValueByKey($key, array $row)
    {
        return $row[$this->positions[$key]];
    }
}

