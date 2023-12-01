<?php

namespace App\Entity;
use DateTime;
use JsonSerializable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FileRepository")
 */
class File implements JsonSerializable
{
    public const TYPE_DOCUMENT = 1;
    public const TYPE_INVOICE = 2;
    public const TYPE_RECAP = 3;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="smallint")
     * @Assert\Choice(callback="getTypes")
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     * @Assert\File(mimeTypes={ "application/pdf", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document", "application/vnd.oasis.opendocument.spreadsheet", "application/vnd.oasis.opendocument.text", "application/vnd.ms-excel", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" })
     */
    private $drive_id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     */
    private $mime;
/**
     * @ORM\Column(type="datetime")
     */
    private $date;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Warrant", inversedBy="files")
     * @ORM\JoinColumn(nullable=false)
     */
    private $warrant;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Invoice", mappedBy="file", cascade={"persist", "remove"})
     */
    private $invoice;

    /**
     * @ORM\OneToOne(targetEntity=Recap::class, mappedBy="file", cascade={"persist", "remove"})
     */
    private $recap;

    private $tmpName;

    public function __construct()
    {
        $this->setMime('application/pdf');
        $this->setDate(new DateTime());
       
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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
    public function getDriveId()
    {
        return $this->drive_id;
    }

    public function setDriveId($drive_id): self
    {
        $this->drive_id = $drive_id;

        return $this;
    }

    public function getMime(): ?string
    {
        return $this->mime;
    }

    public function setMime(string $mime): self
    {
        $this->mime = $mime;

        return $this;
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

    public function getTmpName(): ?string
    {
        return $this->tmpName;
    }

    public function setTmpName(string $tmpName): self
    {
        $this->tmpName = $tmpName;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(Invoice $invoice): self
    {
        $this->invoice = $invoice;

        // set the owning side of the relation if necessary
        if ($this !== $invoice->getFile()) {
            $invoice->setFile($this);
        }

        return $this;
    }

    public function getExtension(): string
    {
        switch ($this->getMime()) {
            case 'application/msword':
                return '.doc';
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return '.docx';
            case 'application/vnd.oasis.opendocument.spreadsheet':
                return '.ods';
            case 'application/vnd.oasis.opendocument.text':
                return '.odt';
            case 'application/vnd.ms-excel':
                return '.xsl';
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                return '.xslx';
            case 'application/pdf':
            default:
                return '.pdf';
        }
    }

    public static function getTypes()
    {
        return [self::TYPE_DOCUMENT, self::TYPE_INVOICE];
    }

    public function getRecap(): ?Recap
    {
        return $this->recap;
    }

    public function setRecap(Recap $recap): self
    {
        // set the owning side of the relation if necessary
        if ($recap->getFile() !== $this) {
            $recap->setFile($this);
        }

        $this->recap = $recap;

        return $this;
    }

    public function jsonSerialize()
    {
        return array(
            'id' => $this->id,
            'drive_id' => $this->drive_id,
            'name' => $this->name,
            'mime' => $this->mime,
            'full_name' => $this->getName().$this->getExtension(),
        );
    }
}
