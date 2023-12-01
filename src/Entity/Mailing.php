<?php

namespace App\Entity;
use App\Entity\PieceJointe;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MailingRepository")
 */
class Mailing
{
    public const STATUS_CREATED = 1;
    public const STATUS_GENERATED = 2;
    public const STATUS_SENT = 3;

    public const TYPE_EVERYONE = 1;
    public const TYPE_BUYERS = 2;
    public const TYPE_SELLERS = 3;

    public const TYPE_E1 = 1;
    public const TYPE_E2 = 2;
    public const TYPE_E3 = 3;

    public const TYPES = [
        self::TYPE_EVERYONE => 'Tous les mandats',
        self::TYPE_BUYERS => 'Mandats vendeurs',
        self::TYPE_SELLERS => 'Mandats acquéreurs',
    ];
    public const TYPES_ENVOI = [
        self::TYPE_E1 => 'Par groupe',
        self::TYPE_E2 => 'Par mandat Individuel',
        self::TYPE_E3 => 'Par mail',
    ];

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $target;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $object;

    /**
     * @ORM\Column(type="text")
     */
    private $content;

    /**
     * @ORM\Column(type="integer")
     */
    private $status;
/**
     * @ORM\OneToOne(targetEntity="App\Entity\PieceJointe", inversedBy="mailing", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $piece_jointe;
    /**
     * @ORM\Column(type="integer")
     */
    private $total;
    /**
     * @ORM\Column(type="integer")
     */
    public $single_target_id;
     /**
     * @ORM\Column(type="string", length=255)
     */
    public $single_target_email;
     /**
     * @ORM\Column(type="string", length=50)
     */
    public $type_envoi;
     /**
     * @ORM\Column(type="string", length=255)
     * 
     * @Assert\File(mimeTypes={ "application/pdf", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document", "application/vnd.oasis.opendocument.spreadsheet", "application/vnd.oasis.opendocument.text", "application/vnd.ms-excel", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" })
     */
    private $piece_jointe_drive_id;
    /**
     * @ORM\Column(type="integer")
     */
    private $sent;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Mail", mappedBy="mailing")
     */
    private $mails;

    public function __construct()
    {
        $this->setStatus(self::STATUS_CREATED);
        $this->setTotal(0);
        $this->setSent(0);
        $this->mails = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(string $target): self
    {
        $this->target = $target;

        return $this;
    }
    public function getPieceJointe(): ?PieceJointe
    {
        return $this->piece_jointe;
    }

    public function setPieceJointe(PieceJointe $piece_jointe): self
    {
        $this->piece_jointe = $piece_jointe;

        return $this;
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
    public function getPieceJointeDriveId()
    {
        return $this->piece_jointe_drive_id;
    }

    public function setPieceJointeDriveId($piece_jointe_drive_id): self
    {
        $this->piece_jointe_drive_id = $piece_jointe_drive_id;

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

    public function addTotal(): ?int
    {
        $this->total++;

        return $this->total;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function addSent(): ?int
    {
        $this->sent++;

        if($this->sent == $this->total)
            $this->setStatus(self::STATUS_SENT);

        return $this->sent;
    }

    public function getSent(): ?int
    {
        return $this->sent;
    }

    public function setSent(int $sent): self
    {
        $this->sent = $sent;

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
            $mail->setMailing($this);
        }

        return $this;
    }

    public function removeMail(Mail $mail): self
    {
        if ($this->mails->contains($mail)) {
            $this->mails->removeElement($mail);
            // set the owning side to null (unless already changed)
            if ($mail->getMailing() === $this) {
                $mail->setMailing(null);
            }
        }

        return $this;
    }

    public function getStatusString(): string
    {
        switch ($this->getStatus()) {
            case self::STATUS_SENT:
                return 'Envoyé';
            case self::STATUS_GENERATED:
                return 'Généré';
            case self::STATUS_CREATED:
            default:
                return 'Créé';
        }
    }
}
