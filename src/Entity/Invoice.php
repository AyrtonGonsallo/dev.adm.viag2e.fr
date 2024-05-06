<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use JsonSerializable;
use Doctrine\ORM\Mapping as ORM;
setlocale(LC_TIME, "fr_FR","French");
/**
 * @ORM\Entity(repositoryClass="App\Repository\InvoiceRepository")
 */
class Invoice implements JsonSerializable
{
    public const CATEGORY_ANNUITY = 0; // Rente
    public const CATEGORY_CONDOMINIUM_FEES = 1; // Co-pro
    public const CATEGORY_GARBAGE = 2; // Ordures
    public const CATEGORY_MANUAL = 3; // Manuel
    public const CATEGORY_AVOIR = 4; // inverse d'une facture / remboursement / avoirs

    public const RECURSION_MONTHLY   = 1; // Mensuel
    public const RECURSION_OTP       = 2; // Exceptionnel
    public const RECURSION_QUARTERLY = 3; // Trimestriel

    public const STATUS_GENERATED = 1;
    public const STATUS_SENT      = 2;
    public const STATUS_UNSENT    = 3;
    public const STATUS_PAYED     = 4;
    public const STATUS_TREATED   = 5;

    public const TYPE_NOTICE_EXPIRY = 1; // Avis d'échéance
    public const TYPE_RECEIPT       = 2; // Quittance
    public const TYPE_AVOIR       = 3;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $number;

    /**
     * @ORM\Column(type="smallint")
     */
    private $type;

    /**
     * @ORM\Column(type="smallint")
     */
    private $category;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    public $date_de_paiement;
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    public $date_generation_avoir;
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    public $date_regeneration;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $data = [];

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Property", inversedBy="invoices")
     * @ORM\JoinColumn(nullable=false)
     */
    private $property;
/**
     * @ORM\OneToOne(targetEntity="App\Entity\FactureMensuelle", inversedBy="invoice", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $facture;
    /**
     * @ORM\OneToOne(targetEntity="App\Entity\File", inversedBy="invoice", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $file;

	/**
     * @ORM\OneToOne(targetEntity="App\Entity\File", inversedBy="invoice", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
	private $file2;
    /**
     * @ORM\Column(type="smallint")
     */
    private $status;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Invoice", inversedBy="invoice", cascade={"persist", "remove"})
     */
    private $receipt;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Invoice", mappedBy="receipt", cascade={"persist", "remove"})
     */
    private $invoice;

    private $payer = [];

    public function __construct()
    {
        $this->setCategory(self::CATEGORY_ANNUITY);
        $this->setDate(new DateTime());
        $this->setStatus(self::STATUS_GENERATED);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

	public function getFile2(): ?File
    {
        return $this->file2;
    }

    public function setFile2(File $file): self
    {
        $this->file2 = $file;

        return $this;
    }

    public function removeFile(int $id)
    {
        if($id==1){
            $this->file = null;
        }else if($id==2){
            $this->file2 = null;
        }  
        
    }
	
    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCategory(): ?int
    {
        return $this->category;
    }

    public function setCategory(int $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }
    
   
    public  function convert_from_latin1_to_utf8_recursively($dat)
    {
      
        if (is_string($dat)) {
            return utf8_encode($dat);
         } elseif (is_array($dat)) {
            $ret = [];
            foreach ($dat as $i => $d) $ret[ $i ] = self::convert_from_latin1_to_utf8_recursively($d);
            return $ret;
         } elseif (is_object($dat)) {
            foreach ($dat as $i => $d) $dat->$i = self::convert_from_latin1_to_utf8_recursively($d);
            return $dat;
         } else {
            return $dat;
         }
         
       
    }
    
    
    public  function convert_from_latin1_to_utf8_recursively2($dat)
    {
      
        if (is_string($dat)) {
            return json_decode($dat, true);
         } elseif (is_array($dat)) {
            $ret = [];
            foreach ($dat as $i => $d) $ret[ $i ] = self::convert_from_latin1_to_utf8_recursively2($d);
            return $ret;
         } elseif (is_object($dat)) {
            foreach ($dat as $i => $d) $dat->$i = self::convert_from_latin1_to_utf8_recursively2($d);
            return $dat;
         } else {
            return $dat;
         }
         
       
    }

    public function getMonthfromNumber($number): ?string{
        
        $date="2024-{$number}-22 12:25:26";
        return utf8_encode(strftime("%B",strtotime($date)));
    
    }
    public function getData(): ?array
    {
        //$this->data = $this->convert_from_latin1_to_utf8_recursively2($this->data);
        $this->data['date']['month']=$this->getMonthfromNumber( $this->data['date']['month_n']);

        return $this->data;
    }


    public function setData(?array $data): self
    {
        $this->data = $this->convert_from_latin1_to_utf8_recursively($data);

        return $this;
    }

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): self
    {
        $this->property = $property;

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(File $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getCategoryString(): string
    {
        switch ($this->getCategory()) {
            case self::CATEGORY_ANNUITY:
                return 'Rente';
            case self::CATEGORY_CONDOMINIUM_FEES:
                return 'Frais de co-pro';
            case self::CATEGORY_GARBAGE:
                return 'Ordures ménagères';
            case self::CATEGORY_AVOIR:
                    return 'Avoir';
            case self::CATEGORY_MANUAL:
            default:
                return 'Manuelle';
        }
    }

    public function getTypeString(): string
    {
        return ($this->getType() == self::TYPE_NOTICE_EXPIRY) ? 'Avis d\'échéance' : 'Quittance';
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusClass(): string
    {
        switch ($this->getStatus()) {
            case self::STATUS_PAYED:
            case self::STATUS_TREATED:
                return 'm--font-success';
            case self::STATUS_SENT:
                return ($this->getType() == self::TYPE_NOTICE_EXPIRY) ? 'm--font-warning' : 'm--font-success';
            case self::STATUS_UNSENT:
            case self::STATUS_GENERATED:
            default:
                return 'm--font-danger';
        }
    }

    public function getStatusString(): string
    {
        switch ($this->getStatus()) {
            case self::STATUS_PAYED:
                return 'Payée';
            case self::STATUS_SENT:
                return 'Envoyée';
            case self::STATUS_UNSENT:
                return 'Erreur d\'envoi';
            case self::STATUS_TREATED:
                return 'Traitée';
            case self::STATUS_GENERATED:
            default:
                return 'Générée';
        }
    }

    public function getReceipt(): ?self
    {
        return $this->receipt;
    }

    public function setReceipt(?self $receipt): self
    {
        $this->receipt = $receipt;

        return $this;
    }

    public function getInvoice(): ?self
    {
        return $this->invoice;
    }

    public function setInvoice(?self $invoice): self
    {
        $this->invoice = $invoice;

        // set (or unset) the owning side of the relation if necessary
        $newReceipt = $invoice === null ? null : $this;
        if ($newReceipt !== $invoice->getReceipt()) {
            $invoice->setReceipt($newReceipt);
        }

        return $this;
    }

    public function getFormattedNumber()
    {
        $res='';
        if($this->getType() == Invoice::TYPE_NOTICE_EXPIRY){
            $res='FA';
        }else if($this->getType() == Invoice::TYPE_AVOIR){
            $res='AV';
        }else{
            $res='QU';
        }
        return $res. str_pad($this->getNumber(), 8, '0', STR_PAD_LEFT);
    }

    public static function formatNumber(int $number, int $type)
    {
        return (($type == Invoice::TYPE_NOTICE_EXPIRY) ? 'FA' : 'QU') . str_pad($number, 8, '0', STR_PAD_LEFT);
    }

    public function getMailTarget()
    {
        $recursion = empty($this->data['recursion']) ? Invoice::RECURSION_MONTHLY : $this->data['recursion'];

        switch ($recursion) {
            /*case Invoice::RECURSION_OTP:
                return [$this->getProperty()->getWarrant()->getMail1(), $this->getProperty()->getWarrant()->getMail2()];*/
            case Invoice::RECURSION_QUARTERLY:
                return $this->getProperty()->getMail1();
            case Invoice::RECURSION_MONTHLY:
            default:
                return (!empty($this->data['separation_type']) && ($this->data['separation_type'] == Property::BUYERS_ANNUITY)) ? $this->getProperty()->getBuyerMail1() : $this->getProperty()->getWarrant()->getMail1();
        }
    }

    public function getMailTargetHonoraire()
    {
        $recursion = empty($this->data['recursion']) ? Invoice::RECURSION_MONTHLY : $this->data['recursion'];

        switch ($recursion) {
            /*case Invoice::RECURSION_OTP:
                return [$this->getProperty()->getWarrant()->getMail1(), $this->getProperty()->getWarrant()->getMail2()];*/
            case Invoice::RECURSION_QUARTERLY:
                return $this->getProperty()->getMail2();
            case Invoice::RECURSION_MONTHLY:
            default:
                return (!empty($this->data['separation_type']) && ($this->data['separation_type'] == Property::BUYERS_ANNUITY)) ? $this->getProperty()->getBuyerMail2() : $this->getProperty()->getWarrant()->getMail2();
        }
    }

    public function getMailCc()
    {
        $recursion = empty($this->data['recursion']) ? Invoice::RECURSION_MONTHLY : $this->data['recursion'];

        switch ($recursion) {
            /*case Invoice::RECURSION_OTP:
                return [$this->getProperty()->getWarrant()->getMail1(), $this->getProperty()->getWarrant()->getMail2()];*/
            case Invoice::RECURSION_QUARTERLY:
                return $this->getProperty()->getMail2();
            case Invoice::RECURSION_MONTHLY:
            default:
            return (!empty($this->data['separation_type']) && ($this->data['separation_type'] == Property::BUYERS_ANNUITY)) ? $this->getProperty()->getBuyerMail2() : $this->getProperty()->getWarrant()->getMail2();
        }
    }

    public function getMailSubject()
    {
        if($this->getCategory() === self::CATEGORY_MANUAL) {
            return $this->getTypeString() . " ".utf8_decode($this->getData()['period'])." .".$this->getProperty()->getTitle();
        }
        return $this->getTypeString() . " {$this->getData()['date']['month']} {$this->getData()['date']['year']} " . $this->getProperty()->getTitle();
    }

    public function getPayer()
    {
        if(empty($this->payer)) {
            $data = $this->getData();

            if($this->getCategory() === self::CATEGORY_CONDOMINIUM_FEES) {
                $this->payer = [
                    'id'        => $this->getProperty()->getId().'02',
                    'firstname' => $data['property']['firstname'],
                    'lastname'  => $data['property']['lastname'],
                    'bic'       => $this->getProperty()->getBankBic(),
                    'iban'      => str_replace(' ', '', $this->getProperty()->getBankIban()),
                    'ics'       => $this->getProperty()->getBankIcs(),
                ];
            }
            elseif($this->getCategory() === self::CATEGORY_ANNUITY) { // was CATEGORY_CONDOMINIUM_FEES ??
                if (!empty($data['warrant']['firstname']) && !empty($data['warrant']['lastname'])) {
                    if ($data['warrant']['firstname'] == $this->getProperty()->getWarrant()->getFirstname() && $data['warrant']['lastname'] == $this->getProperty()->getWarrant()->getLastname()) {
                        $this->payer = [
                            'id'        => $this->getProperty()->getWarrant()->getId() . '00',
                            'firstname' => $data['warrant']['firstname'],
                            'lastname'  => $data['warrant']['lastname'],
                            'bic'       => $this->getProperty()->getWarrant()->getBankBic(),
                            'iban'      => str_replace(' ', '', $this->getProperty()->getWarrant()->getBankIban()),
                            'ics'       => $this->getProperty()->getWarrant()->getBankIcs(),
                        ];
                    } elseif ($data['warrant']['firstname'] == $this->getProperty()->getBuyerFirstname() && $data['warrant']['lastname'] == $this->getProperty()->getBuyerLastname()) {
                        $this->payer = [
                            'id'        => $this->getProperty()->getId() . '01',
                            'firstname' => $data['property']['buyerfirstname'],
                            'lastname'  => $data['property']['buyerlastname'],
                            'bic'       => $this->getProperty()->getBuyerBankBic(),
                            'iban'      => str_replace(' ', '', $this->getProperty()->getBuyerBankIban()),
                            'ics'       => $this->getProperty()->getBuyerBankIcs(),
                        ];
                    }
                } else {
                    $this->payer = [
                        'id'        => $this->getProperty()->getId() . '02',
                        'firstname' => $data['property']['firstname'],
                        'lastname'  => $data['property']['lastname'],
                        'bic'       => $this->getProperty()->getBankBic(),
                        'iban'      => str_replace(' ', '', $this->getProperty()->getBankIban()),
                        'ics'       => $this->getProperty()->getBankIcs(),
                    ];
                }
            }
            elseif($this->getCategory() === self::CATEGORY_AVOIR) { // was CATEGORY_CONDOMINIUM_FEES ??
                if (!empty($data['warrant']['firstname']) && !empty($data['warrant']['lastname'])) {
                    if ($data['warrant']['firstname'] == $this->getProperty()->getWarrant()->getFirstname() && $data['warrant']['lastname'] == $this->getProperty()->getWarrant()->getLastname()) {
                        $this->payer = [
                            'id'        => $this->getProperty()->getWarrant()->getId() . '00',
                            'firstname' => $data['warrant']['firstname'],
                            'lastname'  => $data['warrant']['lastname'],
                            'bic'       => $this->getProperty()->getWarrant()->getBankBic(),
                            'iban'      => str_replace(' ', '', $this->getProperty()->getWarrant()->getBankIban()),
                            'ics'       => $this->getProperty()->getWarrant()->getBankIcs(),
                        ];
                    } elseif ($data['warrant']['firstname'] == $this->getProperty()->getBuyerFirstname() && $data['warrant']['lastname'] == $this->getProperty()->getBuyerLastname()) {
                        $this->payer = [
                            'id'        => $this->getProperty()->getId() . '01',
                            'firstname' => $data['property']['buyerfirstname'],
                            'lastname'  => $data['property']['buyerlastname'],
                            'bic'       => $this->getProperty()->getBuyerBankBic(),
                            'iban'      => str_replace(' ', '', $this->getProperty()->getBuyerBankIban()),
                            'ics'       => $this->getProperty()->getBuyerBankIcs(),
                        ];
                    }
                } else {
                    $this->payer = [
                        'id'        => $this->getProperty()->getId() . '02',
                        'firstname' => $data['property']['firstname'],
                        'lastname'  => $data['property']['lastname'],
                        'bic'       => $this->getProperty()->getBankBic(),
                        'iban'      => str_replace(' ', '', $this->getProperty()->getBankIban()),
                        'ics'       => $this->getProperty()->getBankIcs(),
                    ];
                }
            }
            else {
                if(!empty($data['target'])) {
                    if($data['target'] === PendingInvoice::TARGET_WARRANT) {
                        $this->payer = [
                            'id'        => $this->getProperty()->getWarrant()->getId() . '00',
                            'firstname' => $data['warrant']['firstname'],
                            'lastname'  => $data['warrant']['lastname'],
                            'bic'       => $this->getProperty()->getWarrant()->getBankBic(),
                            'iban'      => str_replace(' ', '', $this->getProperty()->getWarrant()->getBankIban()),
                            'ics'       => $this->getProperty()->getWarrant()->getBankIcs(),
                        ];
                    }
                    elseif($data['target'] === PendingInvoice::TARGET_PROPERTY) {
                        $this->payer = [
                            'id'        => $this->getProperty()->getId() . '02',
                            'firstname' => $data['property']['firstname'],
                            'lastname'  => $data['property']['lastname'],
                            'bic'       => $this->getProperty()->getBankBic(),
                            'iban'      => str_replace(' ', '', $this->getProperty()->getBankIban()),
                            'ics'       => $this->getProperty()->getBankIcs(),
                        ];
                    }
                    elseif($data['target'] === PendingInvoice::TARGET_BUYER) {
                        $this->payer = [
                            'id'        => $this->getProperty()->getId() . '01',
                            'firstname' => $data['property']['buyerfirstname'],
                            'lastname'  => $data['property']['buyerlastname'],
                            'bic'       => $this->getProperty()->getBuyerBankBic(),
                            'iban'      => str_replace(' ', '', $this->getProperty()->getBuyerBankIban()),
                            'ics'       => $this->getProperty()->getBuyerBankIcs(),
                        ];
                    }
                }
            }
        }

        return $this->payer;
    }

    public function jsonSerialize()
    {
        return array(
            'id' => $this->id,
            'status' => $this->status,
            'property'=> $this->property,
            'file'=> $this->file,
            'file2'=> $this->file2,
        );
    }
}
