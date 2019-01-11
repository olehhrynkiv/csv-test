<?php

ini_set('display_errors', 1);

require 'Processor.php';
require_once 'FileReader.php';
require_once 'DbConnector.php';
$config = require 'config.php';

$filename = $argv[1] ?? 'report.csv';
$mapping = [
    Processor::TRANSACTION_DATE        => 'Transaction Date',
    Processor::TRANSACTION_TYPE        => 'Transaction Type',
    Processor::TRANSACTION_CARD_TYPE   => 'Transaction Card Type',
    Processor::TRANSACTION_CARD_NUMBER => 'Transaction Card Number',
    Processor::TRANSACTOIN_AMOUNT      => 'Transaction Amount',
    Processor::BATCH_DATE              => 'Batch Date',
    Processor::BATCH_REF_NUM           => 'Batch Reference Number',
    Processor::MERCHANT_ID             => 'Merchant ID',
    Processor::MERCHANT_NAME           => 'Merchant Name',
];
if (isset($argv[2])) {
    $data = json_decode($argv[2], true);
    if (!empty($data)) {
        $mapping = $data;
    }
}
$dsn = 'mysql:host=' . $config['host'] .';dbname=' . $config['db'];
$connector = new DbConnector($dsn, $config['user'], $config['password']);
$connector->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$reader = new CSVReader($filename);
$processor = new Processor($mapping);
$processor->setReader($reader);
$processor->setConnector($connector);
$processor->process();




