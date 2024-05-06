<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Bic;
use Symfony\Component\Validator\Constraints\Iban;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PropertyRepository")
 */
class Property
{
    public const LIFETIME_TYPE_VL = 1;
    public const LIFETIME_TYPE_VO = 2;
    public const LIFETIME_TYPE_BF_SALE = 3;
    public const LIFETIME_TYPE_FF_SALE = 4;
    public const LIFETIME_TYPE_NP = 5;
    
    public const LIFETIME_TYPES = [
        self::LIFETIME_TYPE_VO => 'VO',
        self::LIFETIME_TYPE_VL => 'VL',
        self::LIFETIME_TYPE_BF_SALE => 'VàTO',
        self::LIFETIME_TYPE_FF_SALE => 'VàTL',
        self::LIFETIME_TYPE_NP => 'NP',
    ];

    public const HEATING_TYPE_ELECTRIC = 1;
    public const HEATING_TYPE_OIL = 2;
    public const HEATING_TYPE_GAS = 3;
    public const HEATING_TYPE_OTHER = 4;

    public const HEATING_TYPES = [
        self::HEATING_TYPE_ELECTRIC => 'Chauffage électrique',
        self::HEATING_TYPE_OIL => 'Chauffage au fioul',
        self::HEATING_TYPE_GAS => 'Chauffage au gaz',
        self::HEATING_TYPE_OTHER => 'Autre type de chauffage'
    ];
    public const intitule_indice_initial_menages_urbains = 1;
    public const intitule_indice_initial_ensemble_des_menages = 2;
  
    public const intitules_indices_initial = [
        self::intitule_indice_initial_ensemble_des_menages => 'Ensemble des ménages',
        self::intitule_indice_initial_menages_urbains => 'Ménages urbains',
        
    ];
    public const BUYERS_HONORARIES = 1;
    public const BUYERS_ANNUITY = 2;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="smallint")
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $title;

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
     * @ORM\Column(type="string", length=100)
     */
    private $country;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $firstname1;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $lastname1;
 /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $acte_abandon_duh_drive_id;

    /**
     * @ORM\Column(type="date")
     */
    private $dateofbirth1;
    /**
     * @ORM\Column(type="date")
     */
    public $date_chaudiere;
    /**
     * @ORM\Column(type="date")
     */
    public $date_cheminee;
    /**
     * @ORM\Column(type="date")
     */
    public $date_climatisation;
/**
     * @ORM\Column(type="date")
     */
    public $date_duh;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $firstname2;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $lastname2;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $dateofbirth2;
    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $mois_indice_initial;
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
    private $good_type;
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    
    private $good_address;	
   
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    
     private $coordonnees_syndic;	
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $property_type;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $construction_year;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $living_space;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $ground_surface;

    /**
     * @ORM\Column(type="smallint")
     */
    private $heating_type;
    /**
     * @ORM\Column(type="smallint")
     */
    private $intitules_indices_initial;

    /**
     * @ORM\Column(type="boolean")
     */
    private $fireplace;
    /**
     * @ORM\Column(type="boolean")
     */
    public $climatisation_pompe_chaleur;
    /**
     * @ORM\Column(type="boolean")
     */
    public $assurance_habitation;
    /**
     * @ORM\Column(type="date", nullable=true)
     */
    public $date_assurance_habitation;
    /**
     * @ORM\Column(type="boolean")
     */
    public $chaudiere;
 /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $designation_du_bien;  
 /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    public $ref_cadastrales;
    /**
     * @ORM\Column(type="smallint")
     */
    private $garage;

    /**
     * @ORM\Column(type="smallint")
     */
    private $parking;

    /**
     * @ORM\Column(type="smallint")
     */
    private $cellar;

    /**
     * @ORM\Column(type="float")
     */
    private $revaluation_index;

    /**
     * @ORM\Column(type="float")
     */
    private $initial_index;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\RevaluationHistory", inversedBy="properties_i")
     */
    public $initial_index_object;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\RevaluationHistory", inversedBy="properties_vir")
     */
    public $valeur_indice_reference_object;
     /**
     * @ORM\Column(type="float")
     */
    public $valeur_indice_ref_og2_i;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\RevaluationHistory", inversedBy="properties_o")
     */
    public $valeur_indice_ref_og2_i_object;
     /**
     * @ORM\Column(type="float")
     */
    public $plafonnement_index_og2_i;
     /**
     * @ORM\Column(type="float")
     */
    public $valeur_indexation_normale;
     /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $commentaire_honoraire;
     /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $commentaire_copro;
     /**
     * @ORM\Column(type="date", nullable=true)
     */
    public $mois_indice_ref_og2_i;
 /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    public $num_mandat_gestion;
 /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    public $codes_syndic;
/**
     * @ORM\Column(type="date", nullable=true)
     */
    public $date_reg_debut;
    /**
     * @ORM\Column(type="date", nullable=true)
     */
    public $date_reg_fin;
    /**
     * @ORM\Column(type="date", nullable=true)
     */
    public $date_debut_gestion_copro;
    /**
     * @ORM\Column(type="date", nullable=true)
     */
    public $date_fin_exercice_copro;
    public $revaluation_history_ogi;
    /**
     * @ORM\Column(type="float")
     */
    private $abandonment_index;

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $revaluation_date;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\File", mappedBy="property")
     */
    private $documents;
/**
     * @ORM\OneToMany(targetEntity="App\Entity\FactureMensuelle", mappedBy="property")
     */
    private $factures;
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $last_revaluation;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    public $date_maj_indice_ref;

    /**
     * @ORM\Column(type="float")
     */
    private $initial_amount;

    /**
     * @ORM\Column(type="float")
     */
    private $honorary_rates;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Honoraire", inversedBy="properties")
     */
    public $honorary_rates_object;

    /**
     * @ORM\Column(type="float")
     */
    private $condominium_fees;
    /**
     * @ORM\Column(type="float")
     */
    public $regul;
    /**
     * @ORM\Column(type="float")
     */
    private $garbage_tax;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $dos_authentic_instrument; // date de signature de l'acte authentique

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $start_date_management; // date de début de gestion viagère

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $end_date_management; // date de fin de contrat

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
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $bank_rum;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $buyer_firstname;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $buyer_lastname;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $buyer_address;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $buyer_postal_code;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $buyer_city;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $buyer_country;

    /**
     * @ORM\Column(type="string", length=15, nullable=true)
     */
    private $buyer_phone1;

    /**
     * @ORM\Column(type="string", length=15, nullable=true)
     */
    private $buyer_phone2;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $buyer_mail1;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $buyer_mail2;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $buyer_bank_establishment_code;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $buyer_bank_code_box;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     */
    private $buyer_bank_account_number;

    /**
     * @ORM\Column(type="string", length=4, nullable=true)
     */
    private $buyer_bank_key;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $buyer_bank_domiciliation;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $buyer_bank_iban;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $buyer_bank_bic;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $buyer_bank_ics;

    /**
     * @ORM\ManyToOne(targetEntity="Warrant", inversedBy="properties")
     * @ORM\JoinColumn(nullable=false)
     */
    private $warrant;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Invoice", mappedBy="property")
     */
    private $invoices;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $last_invoice;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $last_quarterly_invoice;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $last_receipt;

    /**
     * @ORM\Column(type="boolean")
     */
    private $honoraries_disabled;

    /**
     * @ORM\Column(type="boolean")
     */
    private $annuities_disabled;

    /**
     * @ORM\Column(type="boolean")
     */
    private $billing_disabled;

    /**
     * @ORM\Column(type="boolean")
     */
    private $show_duh;
     /**
     * @ORM\Column(type="boolean")
     */
    private $clause_OG2I;	
    /**
     * @ORM\Column(type="boolean")
     */
    private $debirentier_different;
    /**
     * @ORM\Column(type="boolean")
     */
    private $hide_export_monthly;

    /**
     * @ORM\Column(type="boolean")
     */
    private $hide_export_otp;
    /**
     * @ORM\Column(type="boolean")
     */
    public $hide_honorary_export;

    /**
     * @ORM\Column(type="boolean")
     */
    private $hide_export_quarterly;

    /**
     * @ORM\Column(type="boolean")
     */
    private $active;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\RevaluationHistory", mappedBy="property")
     */
    private $revaluationHistories;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Notification", mappedBy="property")
     */
    private $notifications;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="properties")
     * @ORM\JoinColumn(nullable=false)
     */
    private $creationUser;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $editionUser;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nom_debirentier;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $prenom_debirentier;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $addresse_debirentier;
   
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $code_postal_debirentier;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $ville_debirentier;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $pays_debirentier;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $telephone_debirentier;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $email_debirentier;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PendingInvoice", mappedBy="property")
     */
    private $pendingInvoices;
    
    public function __construct()
    {
        $this->setActive(true);
        
        $this->setGoodType(self::LIFETIME_TYPE_VL);
        $this->setHeatingType(self::HEATING_TYPE_ELECTRIC);
        $this->setGarage(0);
        $this->setParking(0);
        $this->setCellar(0);
        $this->setFireplace(false);

        $this->setHonorariesDisabled(false);
        $this->setBillingDisabled(false);
        $this->setRevaluationIndex(0.0);
        $this->setInitialIndex(0.0);
        $this->setAbandonmentIndex(0.0);
        $this->setRevaluationDate('01-01');
        //$this->setInitialAmount(0.0);
        $this->setHonoraryRates(0.0);
        $this->setCondominiumFees(0.0);
        $this->setGarbageTax(0.0);

        $this->setCountry('France');
        $this->setBuyerCountry('France');

        $this->setStartDateManagement(new DateTime());

        $this->setLastInvoice(new DateTime('1970-01-01'));
        $this->setLastQuarterlyInvoice(new DateTime('1970-01-01'));
        $this->setLastReceipt(new DateTime('1970-01-01'));

        $this->setHideExportMonthly(false);
        $this->setHideExportQuarterly(false);
        $this->setHideExportOtp(true);

        $this->invoices = new ArrayCollection();
        $this->revaluationHistories = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->pendingInvoices = new ArrayCollection();
        

    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('bank_iban', new Iban());
        $metadata->addPropertyConstraint('bank_bic', new Bic());
        $metadata->addPropertyConstraint('buyer_bank_iban', new Iban());
        $metadata->addPropertyConstraint('buyer_bank_bic', new Bic());
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

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

    public function getFirstname1(): ?string
    {
        return $this->firstname1;
    }

    public function setFirstname1(string $firstname1): self
    {
        $this->firstname1 = ucwords($firstname1);

        return $this;
    }

    public function getLastname1(): ?string
    {
        return $this->lastname1;
    }

    public function setLastname1(string $lastname1): self
    {
        $this->lastname1 = ucwords($lastname1);

        return $this;
    }

    public function getDateofbirth1(): ?DateTimeInterface
    {
        return $this->dateofbirth1;
    }

    public function setDateofbirth1(DateTimeInterface $dateofbirth1): self
    {
        $this->dateofbirth1 = $dateofbirth1;

        return $this;
    }

    public function getFirstname2(): ?string
    {
        return $this->firstname2;
    }

    public function setFirstname2(?string $firstname2): self
    {
        $this->firstname2 = ucwords($firstname2);

        return $this;
    }

    public function getLastname2(): ?string
    {
        return $this->lastname2;
    }

    public function setLastname2(?string $lastname2): self
    {
        $this->lastname2 = ucwords($lastname2);

        return $this;
    }

    public function getDateofbirth2(): ?DateTimeInterface
    {
        return $this->dateofbirth2;
    }

    public function setDateofbirth2(?DateTimeInterface $dateofbirth2): self
    {
        $this->dateofbirth2 = $dateofbirth2;

        return $this;
    }

    public function getMoisIndiceInitial(): ?DateTimeInterface
    {
        return $this->mois_indice_initial;
    }

    public function setMoisIndiceInitial(?DateTimeInterface $mois_indice_initial): self
    {
        $this->mois_indice_initial = $mois_indice_initial;

        return $this;
    }
   
    public function getCoordonneesSyndic(): ?string
    {
        return $this->coordonnees_syndic;
    }

    public function setCoordonneesSyndic(?string $coordonnees_syndic): self
    {
        $this->coordonnees_syndic = $coordonnees_syndic;

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

    public function getGoodType(): ?int
    {
        return $this->good_type;
    }

    public function setGoodType(int $good_type): self
    {
        $this->good_type = $good_type;

        return $this;
    }

    public function getPropertyType(): ?string
    {
        return $this->property_type;
    }

    public function setPropertyType(?string $property_type): self
    {
        $this->property_type = $property_type;

        return $this;
    }
    public function getGoodAddress(): ?string
    {
        return $this->good_address;
    }

    public function setGoodAddress(?string $good_address): self
    {
        $this->good_address = $good_address;

        return $this;
    }

    public function getConstructionYear(): ?int
    {
        return $this->construction_year;
    }

    public function setConstructionYear(?int $construction_year): self
    {
        $this->construction_year = $construction_year;

        return $this;
    }

    public function getLivingSpace(): ?float
    {
        return $this->living_space;
    }

    public function setLivingSpace(?float $living_space): self
    {
        $this->living_space = $living_space;

        return $this;
    }

    public function getGroundSurface(): ?float
    {
        return $this->ground_surface;
    }

    public function setGroundSurface(?float $ground_surface): self
    {
        $this->ground_surface = $ground_surface;

        return $this;
    }

    public function getHeatingType(): ?int
    {
        return $this->heating_type;
    }

    public function setHeatingType(int $heating_type): self
    {
        $this->heating_type = $heating_type;

        return $this;
    }
    public function getIntitulesIndicesInitial(): ?int
    {
        return $this->intitules_indices_initial;
    }

    public function setIntitulesIndicesInitial(int $intitules_indices_initial): self
    {
        $this->intitules_indices_initial = $intitules_indices_initial;

        return $this;
    }

    public function getFireplace(): ?bool
    {
        return $this->fireplace;
    }

    public function setFireplace(bool $fireplace): self
    {
        $this->fireplace = $fireplace;

        return $this;
    }

    public function getGarage(): ?int
    {
        return $this->garage;
    }

    public function setGarage(int $garage): self
    {
        $this->garage = $garage;

        return $this;
    }

    public function getParking(): ?int
    {
        return $this->parking;
    }

    public function setParking(int $parking): self
    {
        $this->parking = $parking;

        return $this;
    }

    public function getCellar(): ?int
    {
        return $this->cellar;
    }

    public function setCellar(int $cellar): self
    {
        $this->cellar = $cellar;

        return $this;
    }

    public function getRevaluationIndex(): ?float
    {
        return $this->revaluation_index;
    }

    public function setRevaluationIndex($revaluation_index): self
    {
        if (empty($revaluation_index)) {
            $this->revaluation_index = 0.0;
        } else {
            $this->revaluation_index = $revaluation_index;
        }

        return $this;
    }

    public function getInitialIndex(): ?float
    {
        return $this->initial_index;
    }

    public function setInitialIndex(float $initial_index): self
    {
        $this->initial_index = $initial_index;

        return $this;
    }

    public function getAbandonmentIndex(): ?float
    {
        return $this->abandonment_index;
    }

    public function setAbandonmentIndex(float $abandonment_index): self
    {
        $this->abandonment_index = $abandonment_index;

        return $this;
    }

    public function getRevaluationDate(): ?string
    {
        return $this->revaluation_date;
    }

    public function setRevaluationDate(string $revaluation_date): self
    {
        $this->revaluation_date = $revaluation_date;

        return $this;
    }

    public function getLastRevaluation(): ?DateTimeInterface
    {
        return $this->last_revaluation;
    }

    public function setLastRevaluation(?DateTimeInterface $last_revaluation): self
    {
        $this->last_revaluation = $last_revaluation;

        return $this;
    }

    public function getInitialAmount(): ?float
    {
        return $this->initial_amount;
    }

    public function setInitialAmount(float $initial_amount): self
    {
        $this->initial_amount = $initial_amount;

        return $this;
    }

    public function getHonoraryRates(): ?float
    {
        return $this->honorary_rates;
    }

    public function setHonoraryRates(float $honorary_rates): self
    {
        $this->honorary_rates = $honorary_rates;

        return $this;
    }

    public function getCondominiumFees(): ?float
    {
        return $this->condominium_fees;
    }

    public function setCondominiumFees(float $condominium_fees): self
    {
        $this->condominium_fees = $condominium_fees;

        return $this;
    }

    public function getGarbageTax(): ?float
    {
        return $this->garbage_tax;
    }

    public function setGarbageTax(float $garbage_tax): self
    {
        $this->garbage_tax = $garbage_tax;

        return $this;
    }

    public function getDosAuthenticInstrument(): ?DateTimeInterface
    {
        return $this->dos_authentic_instrument;
    }

    public function setDosAuthenticInstrument(?DateTimeInterface $dos_authentic_instrument): self
    {
        $this->dos_authentic_instrument = $dos_authentic_instrument;

        return $this;
    }

    public function getStartDateManagement(): ?DateTimeInterface
    {
        return $this->start_date_management;
    }

    public function setStartDateManagement(?DateTimeInterface $start_date_management): self
    {
        $this->start_date_management = $start_date_management;

        return $this;
    }

    public function getEndDateManagement(): ?DateTimeInterface
    {
        return $this->end_date_management;
    }

    public function setEndDateManagement(?DateTimeInterface $end_date_management): self
    {
        $this->end_date_management = $end_date_management;

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

    public function setBankIcs(?string $bank_ics): self
    {
        $this->bank_ics = $bank_ics;

        return $this;
    }

    public function getBankRum(): ?string
    {
        return $this->bank_rum;
    }

    public function setBankRum(?string $bank_rum): self
    {
        $this->bank_rum = $bank_rum;

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

    public function getBuyerFirstname(): ?string
    {
        return $this->buyer_firstname;
    }

    public function setBuyerFirstname(string $buyer_firstname): self
    {
        $this->buyer_firstname = $buyer_firstname;

        return $this;
    }

    public function getBuyerLastname(): ?string
    {
        return $this->buyer_lastname;
    }

    public function setBuyerLastname(string $buyer_lastname): self
    {
        $this->buyer_lastname = $buyer_lastname;

        return $this;
    }

    public function getBuyerAddress(): ?string
    {
        return $this->buyer_address;
    }

    public function setBuyerAddress(string $buyer_address): self
    {
        $this->buyer_address = $buyer_address;

        return $this;
    }

    public function getBuyerPostalCode(): ?string
    {
        return $this->buyer_postal_code;
    }

    public function setBuyerPostalCode(string $buyer_postal_code): self
    {
        $this->buyer_postal_code = $buyer_postal_code;

        return $this;
    }

    public function getBuyerCity(): ?string
    {
        return $this->buyer_city;
    }

    public function setBuyerCity(string $buyer_city): self
    {
        $this->buyer_city = $buyer_city;

        return $this;
    }

    public function getBuyerCountry(): ?string
    {
        return $this->buyer_country;
    }

    public function setBuyerCountry(string $buyer_country): self
    {
        $this->buyer_country = $buyer_country;

        return $this;
    }

    public function getBuyerPhone1(): ?string
    {
        return $this->buyer_phone1;
    }

    public function setBuyerPhone1(string $buyer_phone1): self
    {
        $this->buyer_phone1 = $buyer_phone1;

        return $this;
    }

    public function getBuyerPhone2(): ?string
    {
        return $this->buyer_phone2;
    }

    public function setBuyerPhone2(?string $buyer_phone2): self
    {
        $this->buyer_phone2 = $buyer_phone2;

        return $this;
    }

    public function getBuyerMail1(): ?string
    {
        return $this->buyer_mail1;
    }

    public function setBuyerMail1(string $buyer_mail1): self
    {
        $this->buyer_mail1 = $buyer_mail1;

        return $this;
    }

    public function getBuyerMail2(): ?string
    {
        return $this->buyer_mail2;
    }

    public function setBuyerMail2(?string $buyer_mail2): self
    {
        $this->buyer_mail2 = $buyer_mail2;

        return $this;
    }

    public function getBuyerBankEstablishmentCode(): ?string
    {
        return $this->buyer_bank_establishment_code;
    }

    public function setBuyerBankEstablishmentCode(?string $buyer_bank_establishment_code): self
    {
        $this->buyer_bank_establishment_code = $buyer_bank_establishment_code;

        return $this;
    }

    public function getBuyerBankCodeBox(): ?string
    {
        return $this->buyer_bank_code_box;
    }

    public function setBuyerBankCodeBox(?string $buyer_bank_code_box): self
    {
        $this->buyer_bank_code_box = $buyer_bank_code_box;

        return $this;
    }

    public function getBuyerBankAccountNumber(): ?string
    {
        return $this->buyer_bank_account_number;
    }

    public function setBuyerBankAccountNumber(?string $buyer_bank_account_number): self
    {
        $this->buyer_bank_account_number = $buyer_bank_account_number;

        return $this;
    }

    public function getBuyerBankKey(): ?string
    {
        return $this->buyer_bank_key;
    }

    public function setBuyerBankKey(?string $buyer_bank_key): self
    {
        $this->buyer_bank_key = $buyer_bank_key;

        return $this;
    }

    public function getBuyerBankDomiciliation(): ?string
    {
        return $this->buyer_bank_domiciliation;
    }

    public function setBuyerBankDomiciliation(?string $buyer_bank_domiciliation): self
    {
        $this->buyer_bank_domiciliation = $buyer_bank_domiciliation;

        return $this;
    }

    public function getBuyerBankIban(): ?string
    {
        return $this->buyer_bank_iban;
    }

    public function setBuyerBankIban(?string $buyer_bank_iban): self
    {
        $this->buyer_bank_iban = $buyer_bank_iban;

        return $this;
    }

    public function getBuyerBankBic(): ?string
    {
        return $this->buyer_bank_bic;
    }

    public function setBuyerBankBic(?string $buyer_bank_bic): self
    {
        $this->buyer_bank_bic = $buyer_bank_bic;

        return $this;
    }
    //----------
    public function getNomDebirentier(): ?string
    {
        return $this->nom_debirentier;
    }

    public function setNomDebirentier(?string $text): self
    {
        $this->nom_debirentier = $text;

        return $this;
    }
    //----------
    public function getPrenomDebirentier(): ?string
    {
        return $this->prenom_debirentier;
    }

    public function setPrenomDebirentier(?string $text): self
    {
        $this->prenom_debirentier = $text;

        return $this;
    }
    //----------
    public function getAddresseDebirentier(): ?string
    {
        return $this->addresse_debirentier;
    }

    public function setAddresseDebirentier(?string $text): self
    {
        $this->addresse_debirentier = $text;

        return $this;
    }
    //----------
    public function getCodePostalDebirentier(): ?string
    {
        return $this->code_postal_debirentier;
    }

    public function setCodePostalDebirentier(?string $text): self
    {
        $this->code_postal_debirentier = $text;

        return $this;
    }
    //----------
    public function getVilleDebirentier(): ?string
    {
        return $this->ville_debirentier;
    }

    public function setVilleDebirentier(?string $text): self
    {
        $this->ville_debirentier = $text;

        return $this;
    }
    //----------
    public function getPaysDebirentier(): ?string
    {
        return $this->pays_debirentier;
    }

    public function setPaysDebirentier(?string $text): self
    {
        $this->pays_debirentier = $text;

        return $this;
    }
    //----------
    public function getTelephoneDebirentier(): ?string
    {
        return $this->telephone_debirentier;
    }

    public function setTelephoneDebirentier(?string $text): self
    {
        $this->telephone_debirentier = $text;

        return $this;
    }
    //----------
    public function getEmailDebirentier(): ?string
    {
        return $this->email_debirentier;
    }

    public function setEmailDebirentier(?string $text): self
    {
        $this->email_debirentier = $text;

        return $this;
    }


    public function getBuyerBankIcs(): ?string
    {
        return $this->buyer_bank_ics;
    }

    public function setBuyerBankIcs(?string $buyer_bank_ics): self
    {
        $this->buyer_bank_ics = $buyer_bank_ics;

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

    /**
     * @return Collection|Invoice[]
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoice $invoice): self
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices[] = $invoice;
            $invoice->setProperty($this);
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): self
    {
        if ($this->invoices->contains($invoice)) {
            $this->invoices->removeElement($invoice);
            // set the owning side to null (unless already changed)
            if ($invoice->getProperty() === $this) {
                $invoice->setProperty(null);
            }
        }

        return $this;
    }

    public function getLastInvoice(): ?DateTimeInterface
    {
        return $this->last_invoice;
    }

    public function setLastInvoice(?DateTimeInterface $last_invoice): self
    {
        $this->last_invoice = $last_invoice;

        return $this;
    }

    public function getLastQuarterlyInvoice(): ?DateTimeInterface
    {
        return $this->last_quarterly_invoice;
    }

    public function setLastQuarterlyInvoice(?DateTimeInterface $last_quarterly_invoice): self
    {
        $this->last_quarterly_invoice = $last_quarterly_invoice;

        return $this;
    }

    public function getLastReceipt(): ?DateTimeInterface
    {
        return $this->last_receipt;
    }

    public function setLastReceipt(?DateTimeInterface $last_receipt): self
    {
        $this->last_receipt = $last_receipt;

        return $this;
    }

    public function hasHonorariesDisabled(): ?bool
    {
        return $this->honoraries_disabled;
    }

    public function setHonorariesDisabled(bool $honoraries_disabled): self
    {
        $this->honoraries_disabled = $honoraries_disabled;

        return $this;
    }

    public function hasAnnuitiesDisabled(): ?bool
    {
        return $this->annuities_disabled;
    }

    public function setAnnuitiesDisabled(bool $annuities_disabled): self
    {
        $this->annuities_disabled = $annuities_disabled;

        return $this;
    }

    public function isBillingDisabled(): ?bool
    {
        return $this->billing_disabled;
    }

    public function setBillingDisabled(bool $billing_disabled): self
    {
        $this->billing_disabled = $billing_disabled;

        return $this;
    }

   

    public function setDebirentierDifferent(bool $debirentier_different): self
    {
        $this->debirentier_different = $debirentier_different;

        return $this;
    }
    public function getDebirentierDifferent(): ?bool
    {
        return $this->debirentier_different;
    }
    public function getShowDuh(): ?bool
    {
        return $this->show_duh;
    }
    public function setShowDuh(bool $show_duh): self
    {
        $this->show_duh = $show_duh;

        return $this;
    }
    public function getClauseOG2I(): ?bool
    {
        return $this->clause_OG2I;
    }
    public function setClauseOG2I(bool $clause_OG2I): self
    {
        $this->clause_OG2I = $clause_OG2I;

        return $this;
    }
    public function getActeAbandonDuhDriveId()
    {
        return $this->acte_abandon_duh_drive_id;
    }

    public function setActeAbandonDuhDriveId($acte_abandon_duh_drive_id): self
    {
        $this->acte_abandon_duh_drive_id = $acte_abandon_duh_drive_id;

        return $this;
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

    public function getHideExportMonthly(): ?bool
    {
        return $this->hide_export_monthly;
    }

    public function setHideExportMonthly(bool $hide_export_monthly): self
    {
        $this->hide_export_monthly = $hide_export_monthly;

        return $this;
    }

    public function getHideExportOtp(): ?bool
    {
        return $this->hide_export_otp;
    }

    public function setHideExportOtp(bool $hide_export_otp): self
    {
        $this->hide_export_otp = $hide_export_otp;

        return $this;
    }

    public function getHideExportQuarterly(): ?bool
    {
        return $this->hide_export_quarterly;
    }

    public function setHideExportQuarterly(bool $hide_export_quarterly): self
    {
        $this->hide_export_quarterly = $hide_export_quarterly;

        return $this;
    }

    /**
     * @return Collection|RevaluationHistory[]
     */
    public function getRevaluationHistories(): Collection
    {
        return $this->revaluationHistories;
    }

    public function addRevaluationHistory(RevaluationHistory $revaluationHistory): self
    {
        if (!$this->revaluationHistories->contains($revaluationHistory)) {
            $this->revaluationHistories[] = $revaluationHistory;
            $revaluationHistory->setProperty($this);
        }

        return $this;
    }

    public function removeRevaluationHistory(RevaluationHistory $revaluationHistory): self
    {
        if ($this->revaluationHistories->contains($revaluationHistory)) {
            $this->revaluationHistories->removeElement($revaluationHistory);
            // set the owning side to null (unless already changed)
            if ($revaluationHistory->getProperty() === $this) {
                $revaluationHistory->setProperty(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Notification[]
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications[] = $notification;
            $notification->setProperty($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->contains($notification)) {
            $this->notifications->removeElement($notification);
            // set the owning side to null (unless already changed)
            if ($notification->getProperty() === $this) {
                $notification->setProperty(null);
            }
        }

        return $this;
    }

    public function getAnnuity(): string
    {
        return ($this->getRevaluationIndex() > 0) ? $this->getRevaluationIndex() / $this->getInitialIndex() * $this->getInitialAmount() : $this->getInitialAmount();
    }

    public function getHonoraries(): string
    {
        return $this->getAnnuity() * $this->getHonoraryRates();
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
     * @return Collection|PendingInvoice[]
     */
    public function getPendingInvoices(): Collection
    {
        return $this->pendingInvoices;
    }

    public function addPendingInvoice(PendingInvoice $pendingInvoice): self
    {
        if (!$this->pendingInvoices->contains($pendingInvoice)) {
            $this->pendingInvoices[] = $pendingInvoice;
            $pendingInvoice->setProperty($this);
        }

        return $this;
    }

    public function removePendingInvoice(PendingInvoice $pendingInvoice): self
    {
        if ($this->pendingInvoices->contains($pendingInvoice)) {
            $this->pendingInvoices->removeElement($pendingInvoice);
            // set the owning side to null (unless already changed)
            if ($pendingInvoice->getProperty() === $this) {
                $pendingInvoice->setProperty(null);
            }
        }

        return $this;
    }

    public function getTargets() {
        return [
            1 => "Mandat: {$this->getWarrant()->getLastname()} {$this->getWarrant()->getFirstname()}",
            2 => "Propriété: {$this->getLastname1()} {$this->getFirstname1()}",
            3 => "Acheteur: {$this->getBuyerLastname()} {$this->getBuyerFirstname()}",
        ];
    }
}
