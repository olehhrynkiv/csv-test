<?php

/**
 * class DbConnector
 *
 * we can also fill whitelist from database via SHOW TABLES and information_schema
 */
class DbConnector extends PDO
{
    /**
     * @var array
     */
    protected static $whitelist = [
        'merchants',
        'batches',
        'transactions'
    ];

    /**
     * build and execute batch insert
     *
     * @param string $table
     * @param array $columns
     * @param array $data
     * @return PDOStatement
     */
    public function doBatchInsert(string $table, array $columns, array $data)
    {
        if (empty($data)) {
            throw new \Exception('Nothing to insert');
        }

        if (!in_array($table, self::$whitelist)) {
            throw new \Exception('Table "' . $table . '" doesn\'t exist');
        }

        $string = 'INSERT IGNORE INTO `' . $table . '` ';
        if (count($columns)) {
            $string .= '(`' . implode('`, `', $columns) . '`) ';
        }
        $string .= 'VALUES';
        $colsCount = count($columns);
        $values = [];
        foreach ($data as $row) {
            $string .= '(' . implode(',', array_fill(0, $colsCount, '?'));
            $values = array_merge($values, array_values($row));
            $string .= '),';
        }
        $string = rtrim($string, ',') . ';';
        $statement = $this->prepare($string);
        return $statement->execute($values);
    }

    /**
     * find merchants by external ids
     *
     * @param array $extIds
     * @return array
     */
    public function getMerchantsByExtIds(array $extIds)
    {
        if (empty($extIds)) {
            return [];
        }
        $statement = $this->prepare('SELECT `ext_merchant_id`, `id` FROM `merchants` WHERE ext_merchant_id in ('
            . join(', ', array_fill(0, count($extIds), '?')) . ');');
        $statement->execute($extIds);
        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);

    }

    /**
     * find batches by external ids
     *
     * @param array $extIds
     * @return array
     */
    public function getBatchesByExtIds(array $extIds)
    {
        if (empty($extIds)) {
            return [];
        }
        $statement = $this->prepare("SELECT `batch_id`, `id` FROM `batches`"
            . "WHERE `batch_id` in ("
            . join(', ', array_fill(0, count($extIds), '?')) . ")");
        $statement->execute($extIds);
        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }


    /**
     * check if import id is unique
     *
     * @param string $importId
     * @return boolean
     */
    public function checkImportIsUnique(string $importId)
    {
        $statement = $this->prepare('SELECT id FROM `transactions` WHERE `import_id` = ? LIMIT 1');
        $statement->execute([$importId]);
        if ($statement->rowCount() > 0) {
            return false;
        }
        return true;
    }

    /**
     * delete all transactions for certain import
     *
     * @param string $importId
     * @return PDOStatement
     */
    public function deleteTransactionsByImport(string $importId)
    {
        $deleteStatement = $this->prepare('DELETE FROM `transactions` WHERE `import_id` = ?');
        $deleteStatement->execute([$importId]);
        return $deleteStatement;
    }
}

