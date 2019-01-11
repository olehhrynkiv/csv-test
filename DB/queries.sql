-- display all transactions for a batch (merchant + date + ref num)
--      date, type, card_type, card_number, amount

SELECT t.`transaction_date`, t.`type`, t.`card_type`, t.`card_number`, t.`amount`
  FROM `transactions` t
    INNER JOIN batches b ON t.batch_id = b.id AND b.batch_date = '2018-05-05' AND b.batch_ref_num = '505625273220766228328886' 
    INNER JOIN merchants m ON b.merchant_id = m.id AND m.ext_merchant_id = '414021196811337258'

-- display stats for a batch
--   per card type (VI - 2 transactions with $100 total, MC - 10 transaction with $200 total)

SELECT t.`card_type` , COUNT( * ) AS transactions_count, t.`type` , SUM( t.`amount` ) AS total
    FROM  `transactions` t
    INNER JOIN batches b ON t.batch_id = b.id AND b.batch_date =  '2018-05-05' AND b.batch_ref_num =  '505625273220766228328886'
    INNER JOIN merchants m ON b.merchant_id = m.id AND m.ext_merchant_id =  '414021196811337258'
    GROUP BY t.card_type

-- display stats for a merchant and a given date range

SELECT COUNT( * ) AS transactions_count, SUM( t.`amount` ) AS total
    FROM  `transactions` t
    INNER JOIN batches b ON t.batch_id = b.id AND b.batch_date BETWEEN '2018-05-01' AND '2018-05-10'
    INNER JOIN merchants m ON b.merchant_id = m.id AND m.ext_merchant_id =  '414021196811337258'
    GROUP BY m.id

-- display top 10 merchants (by total amount) for a given date range
--      merchant id, merchant name, total amount, number of transactions

SELECT m.ext_merchant_id, m.`name`, SUM( t.`amount` ) AS total, COUNT( * ) AS transactions_count
    FROM  `transactions` t
    INNER JOIN batches b ON t.batch_id = b.id AND b.batch_date BETWEEN '2018-05-01' AND '2018-05-10'
    INNER JOIN merchants m ON b.merchant_id = m.id
    GROUP BY m.id
    ORDER BY transactions_count DESC
    LIMIt 10