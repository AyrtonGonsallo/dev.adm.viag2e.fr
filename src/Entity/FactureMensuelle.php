<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;

use Doctrine\ORM\Mapping as ORM;
setlocale(LC_TIME, "fr_FR","French");
/**
 * @ORM\Entity(repositoryClass="App\Repository\FactureMensuelleRepository")
 */
class FactureMensuelle 
{
    

    public const TYPE_RENTE = 1; // rente
    public const TYPE_HONORAIRES       = 2; // honoraires
	public const TYPE_COPRO       = 3; // copro

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
     * @ORM\Column(type="float")
     */
    private $amount;
     /**
     * @ORM\ManyToOne(targetEntity="DestinataireFacture", inversedBy="factures")
     * @ORM\JoinColumn(nullable=false)
     */
    private $destinataire;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;
  

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Property", inversedBy="invoices")
     * @ORM\JoinColumn(nullable=false)
     */
    private $property;

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
  

    public function __construct()
    {
       
        $this->setDate(new DateTime());
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

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getDestinataire(): ?DestinataireFacture
    {
        return $this->destinataire;
    }

    public function setDestinataire(?DestinataireFacture $destinataire): self
    {
        $this->destinataire = $destinataire;

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


    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self
    {
        $this->date = $date;

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

    
    public function getTypeString(): string
    {
		if($this->getType() == self::TYPE_RENTE){
			$type='Rente';
		}
		else if($this->getType() == self::TYPE_HONORAIRES){
			$type='Honoraires';
		}
		else if($this->getType() == self::TYPE_COPRO){
			$type="Copro";
		}
        return $type;
    }

   
    public function jsonSerialize()
    {
        return array(
            'id' => $this->id,
            'property'=> $this->property,
            'file'=> $this->file,
            'file2'=> $this->file2,
        );
    }
}
