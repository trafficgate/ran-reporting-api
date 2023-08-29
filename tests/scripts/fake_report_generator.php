<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Faker\Factory as FakerFactory;
use League\Csv\Writer as CsvWriter;

function enclose($item)
{
    return '"' . $item . '"';
}

$outputFilePath = __DIR__ . '/signature-orders.csv';
$header         = 'Member ID (U1),MID,Advertiser Name,Order ID,Transaction Date,Transaction Time,SKU,Sales,# of Items,Total Commission,Process Date,Process Time';
$newline        = "\r\n";

$outputFile = new SplFileObject($outputFilePath, 'w');
$outputFile->fwrite(CsvWriter::BOM_UTF8 . $header . $newline);

$faker = FakerFactory::create();

foreach (range(1, 1532) as $index) {
    $memberId        = $faker->numerify('#############');
    $mid             = $faker->randomNumber(4);
    $merchantName    = $faker->company;
    $orderId         = $faker->randomNumber(9);
    $transactionDate = $faker->date('n/j/y');
    $transactionTime = $faker->time('H:i:s');
    $sku             = $faker->randomNumber(7);
    $sales           = $faker->randomFloat(2, 0, 100);
    $numberOfItems   = $faker->randomElement([0, 1]);
    $totalCommission = $faker->randomFloat(3, 0, 3);
    $processDate     = $faker->date('n/j/y');
    $processTime     = $faker->time('H:i:s');

    $outputFile->fwrite(implode(',', [
        enclose($memberId),
        $mid,
        enclose($merchantName),
        enclose($orderId),
        $transactionDate,
        enclose($transactionTime),
        enclose($sku),
        $sales,
        $numberOfItems,
        $totalCommission,
        $processDate,
        enclose($processTime),
    ]));
    $outputFile->fwrite($newline);
}
