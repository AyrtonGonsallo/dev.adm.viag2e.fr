<?php
namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Parameter;
use DateTime;
use Doctrine\Persistence\ObjectManager;
use Psr\Container\ContainerInterface;

class Bank
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * @var ObjectManager
     */
    private $manager;

    /**
     * @var Total
     */
    private $total;

    /** @var string */
    private $message_id;

    public function __construct(ContainerInterface $container)
    {
        $this->manager = $container->get('doctrine')->getManager();
    }

    private function buildXML()
    {
        $xml = new \SimpleXMLElement('<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.008.001.02" xmlns:xsi="http://www.w3.org/2001/XMLSchema" xsi:schemaLocation="urn:iso:std:iso:20022:tech:xsd:pain.008.001.02 pain.008.001.02.xsd"/>');

        $repository = $this->manager->getRepository(Parameter::class);
        $iban = $repository->findOneBy(['name' => 'iban'])->getValue();
        $bic = $repository->findOneBy(['name' => 'bic'])->getValue();

        $CstmrDrctDbtInitn = $xml->addChild('CstmrDrctDbtInitn');
        $GrpHdr = $CstmrDrctDbtInitn->addChild('GrpHdr');
        $GrpHdr->addChild('MsgId', $this->message_id);
        $GrpHdr->addChild('CreDtTm', (new DateTime())->format('Y-m-d\TH:i:s'));
        $GrpHdr->addChild('InitgPty')->addChild('Nm', 'Viag2E');
        //$Pties= $CstmrDrctDbtInitn->addChild('properties');
        foreach ($this->data as $property_id => $types) {
            
            foreach ($types as $property) {
                if(empty($property['payer']['ics'])) {//si C'Est acquereur et que le warrant n'a pas d'ics il va sauter, si c'est vendeur et que le buyer n'a pas d'ics il va sauter
                    continue;
                }
                
                //$Pties->addChild('property_id', $property_id);
                //$Pties->addChild('property_title', $property['title']);
                foreach ($property['invoices'] as $invoice) {
                    //$Pties->addChild('invoices_n_'.$invoice['inv_id'], $invoice['inv_id']);
                    //$Pties->addChild('montant_invoice_n_'.$invoice['inv_id'], $invoice['amount']);
                    $PmtInf = $CstmrDrctDbtInitn->addChild('PmtInf');
                    $PmtInf->addChild('PmtMtd', 'DD');
                    $PmtInf->addChild('PmtInfId', $property_id);
                    $PmtInf->addChild('NbOfTxs', $property['total']->getTransactions());
                    $PmtInf->addChild('CtrlSum', $property['total']->getTotal());

                    $PmtTpInf = $PmtInf->addChild('PmtTpInf');
                    $PmtTpInf->addChild('SvcLvl')->addChild('Cd', 'SLEV'); // SEPA ?
                    $PmtTpInf->addChild('LclInstrm')->addChild('Cd', 'CORE');
                    $PmtTpInf->addChild('SeqTp', 'RCUR'); // TODO

                    $PmtInf->addChild('ReqdColltnDt', $invoice['date']->format('Y-m-d'));
                    $PmtInf->addChild('Cdtr')->addChild('Nm', 'Viag2E');
                    $PmtInf->addChild('CdtrAcct')->addChild('Id')->addChild('IBAN', $iban);
                    $PmtInf->addChild('CdtrAgt')->addChild('CdtrAgt')->addChild('BIC', $bic);
                    $PmtInf->addChild('ChrgBr', 'SLEV');

                    $Othr = $PmtInf->addChild('CdtrSchmeId')->addChild('Id')->addChild('PrvtId')->addChild('Othr');
                    $Othr->addChild('Id', $property['payer']['ics']); // FRZZ
                    $Othr->addChild('SchmeNm')->addChild('Prtry', 'SEPA');

                    // loop ?
                    $DrctDbtTxInf = $PmtInf->addChild('DrctDbtTxInf');

                    $PmtId = $DrctDbtTxInf->addChild('PmtId');
                    $PmtId->addChild('InstrId', '0');
                    $PmtId->addChild('EndToEndId', $property['payer']['id']);
                    if( $invoice['type'] == Invoice::TYPE_AVOIR){
                        $InstdAmt = $DrctDbtTxInf->addChild('InstdAmt', -$invoice['amount']);

                    }else{
                        $InstdAmt = $DrctDbtTxInf->addChild('InstdAmt', $invoice['amount']);

                    }
                    $InstdAmt->addAttribute('Ccy', 'EUR');

                    $DrctDbtTx = $DrctDbtTxInf->addChild('DrctDbtTx');
                    $Othr      = $DrctDbtTx->addChild('CdtrSchmeId')->addChild('Id')->addChild('PrvtId')->addChild('Othr');
                    $Othr->addChild('Id', $property['payer']['ics']);
                    $Othr->addChild('SchmeNm')->addChild('Prtry', 'SEPA');
                    $MndtRltdInf = $DrctDbtTx->addChild('MndtRltdInf');
                    $MndtRltdInf->addChild('MndtId', 'PROP NO ' . $property_id);
                    $MndtRltdInf->addChild('DtOfSgntr', $invoice['dos']);

                    $DrctDbtTxInf->addChild('DbtrAgt')->addChild('FinInstnId')->addChild('BIC', $property['payer']['bic']);
                    $DrctDbtTxInf->addChild('Dbtr')->addChild('Nm', $property['payer']['lastname'] . ' ' . $property['payer']['firstname']);
                    $DrctDbtTxInf->addChild('DbtrAcct')->addChild('Id')->addChild('IBAN', $property['payer']['iban']);
                    $DrctDbtTxInf->addChild('RmtInf')->addChild('Ustrd', 'Facture ' . $invoice['number']);
                }
            }
        }

        // Add totals for integrity check
        $GrpHdr->addChild('NbOfTxs', $this->total->getTransactions());
        $GrpHdr->addChild('CtrlSum', $this->total->getTotal());

        return $xml->asXML();
    }

    public function generate(\DateTime $start, \DateTime $end, string $message_id)
    {
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        $this->message_id = $message_id;
        $this->total = new Total();

        $invoices = $this->manager->getRepository(Invoice::class)->listByDate2($start, $end);
        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            $data = $invoice->getData();
            if(empty($data['recursion'])) {
                $data['recursion'] = Invoice::RECURSION_MONTHLY;
            }
/*
            if($data['recursion'] == Invoice::RECURSION_MONTHLY && $invoice->getProperty()->getHideExportMonthly() && $invoice->getFile()) {//si cacher rente et facture de rente
                continue;
            }

            if($data['recursion'] == Invoice::RECURSION_MONTHLY && $invoice->getProperty()->hide_honorary_export && $invoice->getFile2()) {//si cacher honoraire et facture de honoraire
                continue;
            }
*/
            if($data['recursion'] == Invoice::RECURSION_OTP && $invoice->getProperty()->getHideExportOtp()) {
                continue;
            }

            if($data['recursion'] == Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getHideExportQuarterly()) {
                continue;
            }

            $amount = $this->getAmount($data,$invoice->getProperty()->hide_honorary_export,$invoice->getProperty()->getHideExportMonthly());

            if($amount <= 0) {
                continue;
            }

            if(empty($this->data[$invoice->getProperty()->getId()])) {
                $this->data[$invoice->getProperty()->getId()] = [];
            }

            if(empty($this->data[$invoice->getProperty()->getId()][$data['recursion']]) || empty($this->data[$invoice->getProperty()->getId()][$data['recursion']]['payer'])) {
                $this->data[$invoice->getProperty()->getId()][$data['recursion']] = [
                    'payer' => $invoice->getPayer(),
                    'title' => $invoice->getProperty()->getTitle(),
                    'type' => $data['recursion'],
                    'total' => new Total(),
                    'invoices' => []
                ];
            }

            if(empty($this->data[$invoice->getProperty()->getId()][$data['recursion']]['payer']['ics'])) {
                continue; //si C'Est acquereur et que le warrant n'a pas d'ics il va sauter, si c'est vendeur et que le buyer n'a pas d'ics il va sauter exemple sur le bien 26
            }
            if($invoice->getType() == Invoice::TYPE_AVOIR) {
                $this->total->addTransaction(-$amount);
                $this->data[$invoice->getProperty()->getId()][$data['recursion']]['total']->addTransaction(-$amount);            
            }else{
                $this->total->addTransaction($amount);
                $this->data[$invoice->getProperty()->getId()][$data['recursion']]['total']->addTransaction($amount);
            }
            

            $this->data[$invoice->getProperty()->getId()][$data['recursion']]['invoices'][] = [
                'amount' => $amount,
                'inv_id' => $invoice->getId(),
                'date'   => $invoice->getDate(),
                'type'   =>$invoice->getType(),
                'dos'    => $invoice->getProperty()->getDosAuthenticInstrument()->format('Y-m-d'),
                'number' => $invoice->getFormattedNumber(),
            ];
        }

        return $this->buildXML();
    }


    public function generate_fa(\DateTime $start, \DateTime $end, string $message_id)
    {
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        $this->message_id = $message_id;
        $this->total = new Total();

        $invoices = $this->manager->getRepository(Invoice::class)->listByDateNE($start, $end);
        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            $data = $invoice->getData();
            if(empty($data['recursion'])) {
                $data['recursion'] = Invoice::RECURSION_MONTHLY;
            }
/*
            if($data['recursion'] == Invoice::RECURSION_MONTHLY && $invoice->getProperty()->getHideExportMonthly() && $invoice->getFile()) {//si cacher rente et facture de rente
                continue;
            }

            if($data['recursion'] == Invoice::RECURSION_MONTHLY && $invoice->getProperty()->hide_honorary_export && $invoice->getFile2()) {//si cacher honoraire et facture de honoraire
                continue;
            }
*/
            if($data['recursion'] == Invoice::RECURSION_OTP && $invoice->getProperty()->getHideExportOtp()) {
                continue;
            }

            if($data['recursion'] == Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getHideExportQuarterly()) {
                continue;
            }

            $amount = $this->getAmount($data,$invoice->getProperty()->hide_honorary_export,$invoice->getProperty()->getHideExportMonthly());

            if($amount <= 0) {
                continue;
            }

            if(empty($this->data[$invoice->getProperty()->getId()])) {
                $this->data[$invoice->getProperty()->getId()] = [];
            }

            if(empty($this->data[$invoice->getProperty()->getId()][$data['recursion']]) || empty($this->data[$invoice->getProperty()->getId()][$data['recursion']]['payer'])) {
                $this->data[$invoice->getProperty()->getId()][$data['recursion']] = [
                    'payer' => $invoice->getPayer(),
                    'title' => $invoice->getProperty()->getTitle(),
                    'type' => $data['recursion'],
                    'total' => new Total(),
                    'invoices' => []
                ];
            }

            if(empty($this->data[$invoice->getProperty()->getId()][$data['recursion']]['payer']['ics'])) {
                continue; //si C'Est acquereur et que le warrant n'a pas d'ics il va sauter, si c'est vendeur et que le buyer n'a pas d'ics il va sauter exemple sur le bien 26
            }
            if($invoice->getType() == Invoice::TYPE_AVOIR) {
                $this->total->addTransaction(-$amount);
                $this->data[$invoice->getProperty()->getId()][$data['recursion']]['total']->addTransaction(-$amount);            
            }else{
                $this->total->addTransaction($amount);
                $this->data[$invoice->getProperty()->getId()][$data['recursion']]['total']->addTransaction($amount);
            }
            

            $this->data[$invoice->getProperty()->getId()][$data['recursion']]['invoices'][] = [
                'amount' => $amount,
                'inv_id' => $invoice->getId(),
                'date'   => $invoice->getDate(),
                'type'   =>$invoice->getType(),
                'dos'    => $invoice->getProperty()->getDosAuthenticInstrument()->format('Y-m-d'),
                'number' => $invoice->getFormattedNumber(),
            ];
        }

        return $this->buildXML();
    }

    private function getAmount(?array $data,$invoice_honn,$invoice_rente)
    {
        switch ($data['recursion']) {
            case Invoice::RECURSION_OTP:
                $amount = isset($data['amount']) && $data['amount'] !== null
                    ? $data['amount']
                    : (isset($data['montantttc']) ? $data['montantttc'] : 0);
            
                return number_format($amount, 2, '.', '');
                break;
            case Invoice::RECURSION_QUARTERLY:
                return number_format($data['property']['condominiumFees'], 2, '.', '');
                break;
            case Invoice::RECURSION_MONTHLY:
                $return=0;
                if( $invoice_honn && $invoice_rente){
                    $return=0;
                }
                else if( $invoice_honn){
                    $return=number_format($data['property']['annuity'], 2, '.', '');
                }else if($invoice_rente){
                    $return=number_format($data['property']['honoraryRates'], 2, '.', '');
                }
                else if( !$invoice_honn && !$invoice_rente){
                    $return=number_format($data['property']['annuity'] + $data['property']['honoraryRates'], 2, '.', '');
                }
                return $return;
                break;
            default:
                return number_format($data['property']['annuity'] + $data['property']['honoraryRates'], 2, '.', '');
                break;
        }
    }
}