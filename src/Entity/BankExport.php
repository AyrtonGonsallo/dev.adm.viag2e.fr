<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\BankExportRepository")
 */
class BankExport
{
    public const TYPE_AUTO = 1;
    public const TYPE_MANUAL = 2;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=100)
     * @Assert\NotBlank()
     */
    private $message_id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     */
    private $period;

    /**
     * @ORM\Column(type="smallint")
     * @Assert\Choice(callback="getTypes")
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     */
    private $drive_id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    public function __construct()
    {
        $this->setDate(new \DateTime());
        $this->setMessageId(md5(random_bytes(12)));
        $this->setType(self::TYPE_AUTO);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessageId(): ?string
    {
        return $this->message_id;
    }

    public function setMessageId(string $message_id): self
    {
        $this->message_id = $message_id;

        return $this;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(string $period): self
    {
        $this->period = $period;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function getTypeString(): ?string
    {
        return ($this->type === self::TYPE_AUTO) ? 'Automatique' : 'Manuel';
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDriveId(): ?string
    {
        return $this->drive_id;
    }

    public function setDriveId(string $drive_id): self
    {
        $this->drive_id = $drive_id;

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

    public function getName(): string
    {
        return 'Export ' . str_replace('/', '-', $this->getPeriod()) . '.xml';
    }

    public static function getTypes()
    {
        return [self::TYPE_AUTO, self::TYPE_MANUAL];
    }
}
