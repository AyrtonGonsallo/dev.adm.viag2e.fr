<?php

namespace App\Entity;
use JsonSerializable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RevaluationHistoryRepository")
 */
class RevaluationHistory implements JsonSerializable
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="float")
     */
    private $value;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Property", mappedBy="valeur_indice_reference_object")
     */
    public $properties_vir;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Property", mappedBy="valeur_indice_ref_og2_i_object")
     */
    public $properties_o;
     /**
     * @ORM\OneToMany(targetEntity="App\Entity\Property", mappedBy="initial_index_object")
     */
    public $properties_i;
    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $type;

    public function __construct()
    {
        $this->setDate(new \DateTime());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(float $value): self
    {
        $this->value = $value;

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
    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

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

    public function jsonSerialize()
    {
        return array(
            'id' => $this->id,
            'type' => $this->type,
            'comment'=> $this->comment,
            'date'=> $this->date,
            'value'=> $this->value,
        );
    }
}
