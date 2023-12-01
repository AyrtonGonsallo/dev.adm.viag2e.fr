<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints\Bic;
use Symfony\Component\Validator\Constraints\Iban;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @ORM\Entity(repositoryClass="App\Repository\WarrantRepository")
 */
class Warrant
{
    public const TYPE_BUYERS = 1;
    public const TYPE_SELLERS = 2;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private $client_id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $firstname;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $lastname;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $address;

    /**
     * @ORM\Column(type="string", length=15)
     */
    private $postal_code;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $city;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $country;

    /**
     * @ORM\Column(type="string", length=15, nullable=true)
     */
    private $phone1;

    /**
     * @ORM\Column(type="string", length=15, nullable=true)
     */
    private $phone2;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $fact_address;

    /**
     * @ORM\Column(type="string", length=15, nullable=true)
     */
    private $fact_postal_code;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $fact_city;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $fact_country;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $bank_establishment_code;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $bank_code_box;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     */
    private $bank_account_number;

    /**
     * @ORM\Column(type="string", length=4, nullable=true)
     */
    private $bank_key;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $bank_domiciliation;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $bank_iban;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $bank_bic;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $bank_ics;
     /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $rum;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Property", mappedBy="warrant")
     */
    private $properties;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $mail1;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $mail2;

    /**
     * @ORM\Column(type="smallint")
     */
    private $type;

    /**
     * @ORM\Column(type="boolean")
     */
    private $active;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\File", mappedBy="warrant")
     */
    private $files;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="warrants")
     * @ORM\JoinColumn(nullable=false)
     */
    private $creationUser;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $editionUser;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Mail", mappedBy="warrant")
     */
    private $mails;

    public function __construct()
    {
        $this->setActive(true);
        $this->setCountry('France');
        $this->properties = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->mails = new ArrayCollection();
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('firstname', new NotBlank());
        $metadata->addPropertyConstraint('lastname', new NotBlank());
        $metadata->addPropertyConstraint('address', new NotBlank());
        $metadata->addPropertyConstraints('postal_code', [new NotBlank(), new Length(["min" => 5, "max" => 5])]);
        $metadata->addPropertyConstraint('city', new NotBlank());
        $metadata->addPropertyConstraint('country', new NotBlank());
        $metadata->addPropertyConstraint('bank_iban', new Iban());
        $metadata->addPropertyConstraint('bank_bic', new Bic());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClientId(): ?string
    {
        return $this->client_id;
    }

    public function setClientId(string $client_id): self
    {
        $this->client_id = $client_id;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstName): self
    {
        $this->firstname = ucwords($firstName);

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastName): self
    {
        $this->lastname = ucwords($lastName);

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

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = ucwords($country);

        return $this;
    }

    public function getPhone1(): ?string
    {
        return $this->phone1;
    }

    public function setPhone1(?string $phone1): self
    {
        $this->phone1 = $phone1;

        return $this;
    }

    public function getPhone2(): ?string
    {
        return $this->phone2;
    }

    public function setPhone2(?string $phone2): self
    {
        $this->phone2 = $phone2;

        return $this;
    }

    public function getFactAddress(): ?string
    {
        return $this->fact_address;
    }

    public function setFactAddress(?string $fact_address): self
    {
        $this->fact_address = $fact_address;

        return $this;
    }

    public function getFactPostalCode(): ?string
    {
        return $this->fact_postal_code;
    }

    public function setFactPostalCode(?string $fact_postal_code): self
    {
        $this->fact_postal_code = $fact_postal_code;

        return $this;
    }

    public function getFactCity(): ?string
    {
        return $this->fact_city;
    }

    public function setFactCity(?string $fact_city): self
    {
        $this->fact_city = $fact_city;

        return $this;
    }

    public function getFactCountry(): ?string
    {
        return $this->fact_country;
    }

    public function setFactCountry(?string $fact_country): self
    {
        $this->fact_country = $fact_country;

        return $this;
    }

    public function getBankEstablishmentCode(): ?string
    {
        return $this->bank_establishment_code;
    }

    public function setBankEstablishmentCode(?string $bank_establishment_code): self
    {
        $this->bank_establishment_code = $bank_establishment_code;

        return $this;
    }

    public function getBankCodeBox(): ?string
    {
        return $this->bank_code_box;
    }

    public function setBankCodeBox(?string $bank_code_box): self
    {
        $this->bank_code_box = $bank_code_box;

        return $this;
    }

    public function getBankAccountNumber(): ?string
    {
        return $this->bank_account_number;
    }

    public function setBankAccountNumber(?string $bank_account_number): self
    {
        $this->bank_account_number = $bank_account_number;

        return $this;
    }

    public function getBankKey(): ?string
    {
        return $this->bank_key;
    }

    public function setBankKey(?string $bank_key): self
    {
        $this->bank_key = $bank_key;

        return $this;
    }

    public function getBankDomiciliation(): ?string
    {
        return $this->bank_domiciliation;
    }

    public function setBankDomiciliation(?string $bank_domiciliation): self
    {
        $this->bank_domiciliation = $bank_domiciliation;

        return $this;
    }

    public function getBankIban(): ?string
    {
        return $this->bank_iban;
    }

    public function setBankIban(?string $bank_iban): self
    {
        $this->bank_iban = $bank_iban;

        return $this;
    }

    public function getBankBic(): ?string
    {
        return $this->bank_bic;
    }

    public function setBankBic(?string $bank_bic): self
    {
        $this->bank_bic = $bank_bic;

        return $this;
    }

    public function getBankIcs(): ?string
    {
        return $this->bank_ics;
    }

    public function setrum(?string $rum): self
    {
        $this->rum = $rum;

        return $this;
    }

    public function getrum(): ?string
    {
        return $this->rum;
    }

    public function setBankIcs(?string $bank_ics): self
    {
        $this->bank_ics = $bank_ics;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return Collection|Property[]
     */
    public function getProperties(): Collection
    {
        return $this->properties;
    }

    public function addProperty(Property $property): self
    {
        if (!$this->properties->contains($property)) {
            $this->properties[] = $property;
            $property->setWarrant($this);
        }

        return $this;
    }

    public function removeProperty(Property $property): self
    {
        if ($this->properties->contains($property)) {
            $this->properties->removeElement($property);
            // set the owning side to null (unless already changed)
            if ($property->getWarrant() === $this) {
                $property->setWarrant(null);
            }
        }

        return $this;
    }

    public function getMail1(): ?string
    {
        return $this->mail1;
    }

    public function setMail1(?string $mail1): self
    {
        $this->mail1 = $mail1;

        return $this;
    }

    public function getMail2(): ?string
    {
        return $this->mail2;
    }

    public function setMail2(?string $mail2): self
    {
        $this->mail2 = $mail2;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function getTypeString(): string
    {
        return self::getTypeName($this->getType());
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public static function getTypeId(string $type): int
    {
        if ($type === 'buyers') {
            return self::TYPE_BUYERS;
        }

        return self::TYPE_SELLERS;
    }

    public static function getTypeName(int $type): ?string
    {
        switch ($type) {
            case self::TYPE_BUYERS:
                return 'buyers';
            case self::TYPE_SELLERS:
                return 'sellers';
        }

        return null;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return Collection|File[]
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(File $file): self
    {
        if (!$this->files->contains($file)) {
            $this->files[] = $file;
            $file->setWarrant($this);
        }

        return $this;
    }

    public function removeFile(File $file): self
    {
        if ($this->files->contains($file)) {
            $this->files->removeElement($file);
            // set the owning side to null (unless already changed)
            if ($file->getWarrant() === $this) {
                $file->setWarrant(null);
            }
        }

        return $this;
    }

    public function hasFactAddress()
    {
        return !empty($this->getFactAddress()) && !empty($this->getFactPostalCode()) && !empty($this->getFactCity());
    }

    public function getCreationUser(): ?User
    {
        return $this->creationUser;
    }

    public function setCreationUser(?User $creationUser): self
    {
        $this->creationUser = $creationUser;

        return $this;
    }

    public function getEditionUser(): ?User
    {
        return $this->editionUser;
    }

    public function setEditionUser(?User $editionUser): self
    {
        $this->editionUser = $editionUser;

        return $this;
    }

    /**
     * @return Collection|Mail[]
     */
    public function getMails(): Collection
    {
        return $this->mails;
    }

    public function addMail(Mail $mail): self
    {
        if (!$this->mails->contains($mail)) {
            $this->mails[] = $mail;
            $mail->setWarrant($this);
        }

        return $this;
    }

    public function removeMail(Mail $mail): self
    {
        if ($this->mails->contains($mail)) {
            $this->mails->removeElement($mail);
            // set the owning side to null (unless already changed)
            if ($mail->getWarrant() === $this) {
                $mail->setWarrant(null);
            }
        }

        return $this;
    }
}
