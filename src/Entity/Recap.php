<?php

namespace App\Entity;

use App\Repository\RecapRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RecapRepository::class)
 */
class Recap
{
    public const GENERATION_TYPE_DISTINCT = 1;
    public const GENERATION_TYPE_FULL = 2;

    public const TYPE_FULL      = -1;
    public const TYPE_BUYER      = 1;
    public const TYPE_SELLER     = 2;
    public const TYPE_HONORARIES = 3;

    public const STATUS_GENERATED = 1;
    public const STATUS_SENT      = 2;
    public const STATUS_UNSENT    = 3;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $year;

    /**
     * @ORM\ManyToOne(targetEntity=Property::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $property;

    /**
     * @ORM\OneToOne(targetEntity=File::class, inversedBy="recap", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $file;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $data = [];

    /**
     * @ORM\Column(type="smallint")
     */
    private $type;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\Column(type="smallint")
     */
    private $status;

    public function __construct()
    {
        $this->setDate(new DateTime());
        $this->setStatus(self::STATUS_GENERATED);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

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

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): self
    {
        $this->data = $data;

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
        switch ($this->getType()) {
            case Recap::TYPE_FULL:
                return'rentes viagères et honoraires';
            case Recap::TYPE_BUYER:
                return ($this->data['warrant']['type'] === Warrant::TYPE_SELLERS) ? 'rentes viagères perçues' : 'rentes viagères versées';
            case Recap::TYPE_SELLER:
                return 'rentes viagères perçues';
            case Recap::TYPE_HONORARIES:
                return 'honoraires versés';
        }
    }

    public function getFileTypeString(): ?string
    {
        switch ($this->getType()) {
            case Recap::TYPE_FULL:
                return '';
            case Recap::TYPE_BUYER:
                return ($this->data['warrant']['type'] === Warrant::TYPE_SELLERS) ? 'Acquéreur mandat vendeur' : 'Acquéreur mandat acquéreur';
            case Recap::TYPE_SELLER:
                return ($this->data['warrant']['type'] === Warrant::TYPE_SELLERS) ? 'Vendeur mandat vendeur' : 'Vendeur mandat acquéreur';
            case Recap::TYPE_HONORARIES:
                return ($this->data['warrant']['type'] === Warrant::TYPE_SELLERS) ? 'Honoraires mandat vendeur' : 'Honoraires mandat acquéreur';
        }
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

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getMailRecipient(): ?string {
        switch ($this->getType()) {
            case Recap::TYPE_FULL:
                return $this->data['warrant']['mail'];
            case Recap::TYPE_BUYER:
                return ($this->data['warrant']['type'] === Warrant::TYPE_SELLERS) ? $this->data['buyer']['mail'] : $this->data['warrant']['mail'];
            case Recap::TYPE_SELLER:
                return ($this->data['warrant']['type'] === Warrant::TYPE_SELLERS) ? $this->data['warrant']['mail'] : $this->data['property']['mail'];
            case Recap::TYPE_HONORARIES:
                return $this->data['warrant']['mail'];
        }
    }

    public function getMailCc(): ?string {
        switch ($this->getType()) {
            case Recap::TYPE_FULL:
                return $this->data['warrant']['mail_cc'];
            case Recap::TYPE_BUYER:
                return ($this->data['warrant']['type'] === Warrant::TYPE_SELLERS) ? $this->data['buyer']['mail_cc'] : $this->data['warrant']['mail_cc'];
            case Recap::TYPE_SELLER:
                return ($this->data['warrant']['type'] === Warrant::TYPE_SELLERS) ? $this->data['warrant']['mail_cc'] : $this->data['property']['mail_cc'];
            case Recap::TYPE_HONORARIES:
                return $this->data['warrant']['mail_cc'];
        }
    }

    public function getMailSubject(): ?string {
        return 'Récapitulatif annuel des ' . (($this->getType() === self::TYPE_HONORARIES) ? 'honoraires' : 'rentes') . ' '. $this->data['year'] .' - ' . $this->getProperty()->getId() . ' - ' . $this->getProperty()->getLastname1() . ((!empty($this->getProperty()->getBuyerLastname())) ? '/' . $this->getProperty()->getBuyerLastname() : null);
    }

    public function test(): string {
        return 'me@marechoux.com';
    }
}
