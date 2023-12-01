<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MailRepository")
 */
class Mail
{
    public const STATUS_GENERATED = 1;
    public const STATUS_SENT = 2;
    public const STATUS_UNSENT = 3;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $object;

    /**
     * @ORM\Column(type="text")
     */
    private $content;

    /**
     * @ORM\Column(type="smallint")
     */
    private $status;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Warrant", inversedBy="mails")
     * @ORM\JoinColumn(nullable=true)
     */
    private $warrant;
    /**
     * @ORM\Column(type="integer")
     */
    public $property_id;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    private $user;
 /**
     * @ORM\Column(type="boolean")
     */
    private $etat;
    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Mailing", inversedBy="mails")
     */
    private $mailing;

    public function __construct()
    {
        $this->setDate(new DateTime());
        $this->setStatus(self::STATUS_GENERATED);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getObject(): ?string
    {
        return $this->object;
    }

    public function setObject(string $object): self
    {
        $this->object = $object;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

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
    public function getEtat(): ?int
    {
        return $this->etat;
    }

    public function setEtat(int $etat): self
    {
        $this->etat = $etat;

        return $this;
    }

    public function getStatusClass(): string
    {
        switch ($this->getStatus()) {
            case self::STATUS_SENT:
                return 'm--font-success';
            case self::STATUS_UNSENT:
                return 'm--font-danger';
            case self::STATUS_GENERATED:
            default:
                return 'm--font-warning';
        }
    }

    public function getStatusString(): string
    {
        switch ($this->getStatus()) {
            case self::STATUS_SENT:
                return 'Envoyé';
            case self::STATUS_UNSENT:
                return 'Erreur d\'envoi';
            case self::STATUS_GENERATED:
            default:
                return 'Généré';
        }
    }

    public function getWarrant(): ?Warrant
    {
        return $this->warrant;
    }

    public function setWarrant(?Warrant $warrant): self
    {
        $this->warrant = $warrant;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

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

    public static function getReplacers(Warrant $warrant) {
        if($warrant){
            return [
                'id'         => $warrant->getId(),
                'numero'     => $warrant->getClientId(),
                'number'     => $warrant->getClientId(),
                'prenom'     => $warrant->getFirstname(),
                'firstname'  => $warrant->getFirstname(),
                'nom'        => $warrant->getLastname(),
                'lastname'   => $warrant->getLastname(),
                'adresse'    => $warrant->getAddress(),
                'adress'     => $warrant->getAddress(),
                'cp'         => $warrant->getPostalCode(),
                'pc'         => $warrant->getPostalCode(),
                'ville'      => $warrant->getCity(),
                'city'       => $warrant->getCity(),
                'pays'       => $warrant->getCountry(),
                'country'    => $warrant->getCountry(),
                'proprietes' => $warrant->getProperties()->count(),
                'properties' => $warrant->getProperties()->count(),
            ];
        }else{
            return null;
        }
        
    }

    public function getMailing(): ?Mailing
    {
        return $this->mailing;
    }

    public function setMailing(?Mailing $mailing): self
    {
        $this->mailing = $mailing;

        return $this;
    }
}
