<?php

namespace App\Entity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DestinataireFactureRepository")
 */
class DestinataireFacture 
{

    public const TYPE_CREDIRENTIER = 1; // crédirentier
    public const TYPE_DEBIRENTIER      = 2; // débirentier
    public const TYPE_MANDANT       = 3; // mandant
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $address;

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $postal_code;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $city;
    
      /**
     * @ORM\OneToMany(targetEntity="App\Entity\FactureMensuelle", mappedBy="destinataire")
     */
    private $factures;
   
     /**
     * @ORM\Column(type="smallint")
     */
    private $type;
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $name;
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $email;

    public function __construct()
    {
        $this->setDate(new \DateTime());
        $this->factures= new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
/**
     * @return Collection|FactureMensuelle[]
     */
    public function getFactures(): Collection
    {
        return $this->factures;
    }

    public function addFactureMensuelle(FactureMensuelle $facture): self
    {
        if (!$this->factures->contains($facture)) {
            $this->factures[] = $facture;
            $facture->setDestinataire($this);
        }

        return $this;
    }

    public function removeFactureMensuelle(FactureMensuelle $facture): self
    {
        if ($this->factures->contains($facture)) {
            $this->factures->removeElement($facture);
            // set the owning side to null (unless already changed)
            if ($facture->getDestinataire() === $this) {
                $facture->setDestinataire(null);
            }
        }

        return $this;
    }
    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postal_code;
    }

    public function setPostalCode(string $postal_code): self
    {
        $this->postal_code = $postal_code;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = ucwords($city);

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
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

    public function getTypeString(): string
    {
        if($this->getType() == self::TYPE_CREDIRENTIER ){
            $label="crédirentier";
        }
        else if($this->getType() == self::TYPE_DEBIRENTIER){
            $label="débirentier";
        }
        else{
            $label="mandant";
        }
        return $label;
    }
  
}
