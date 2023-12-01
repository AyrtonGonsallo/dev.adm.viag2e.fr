<?php
namespace App\Service;

class Total
{
    private $total        = 0;
    private $transactions = 0;

    public function __construct() {}

    public function addTransaction($amount)
    {
        $this->total += $amount;
        $this->transactions += 1;
    }

    public function getTotal()
    {
        return number_format($this->total, 2, '.', '');
    }

    public function getTransactions()
    {
        return $this->transactions;
    }
}
