<?php

namespace App\Form;
use App\Entity\RevaluationHistory;
use App\Entity\Property;
use App\Entity\Warrant;
use App\Entity\Honoraire;
use App\Form\Type\DayType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
setlocale(LC_TIME, 'fr_FR.UTF8', 'fr.UTF8', 'fr_FR.UTF-8', 'fr.UTF-8');

class PropertyFormBuyerType extends AbstractType
{
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
                ->add('buyerFirstname', TextType::class, ['required' => false])
                ->add('buyerLastname', TextType::class, ['required' => false])
                ->add('buyerAddress', TextType::class, ['required' => false])
                ->add('buyerPostalCode', TextType::class, ['required' => false])
                ->add('buyerCity', TextType::class, ['required' => false])
                ->add('buyerCountry', TextType::class, ['required' => false])
                ->add('buyerPhone1', TextType::class, ['required' => false])
                ->add('buyerPhone2', TextType::class, ['required' => false])
                ->add('buyerMail1', TextType::class, ['required' => false])
                ->add('buyerMail2', TextType::class, ['required' => false])

                ->add('buyer_bank_establishment_code', TextType::class, ['required' => false])
                ->add('buyer_bank_code_box', TextType::class, ['required' => false])
                ->add('buyer_bank_account_number', TextType::class, ['required' => false])
                ->add('buyer_bank_key', TextType::class, ['required' => false])
                ->add('buyer_bank_domiciliation', TextType::class, ['required' => false])
                ->add('buyer_bank_iban', TextType::class, ['required' => false])
                ->add('buyer_bank_bic', TextType::class, ['required' => false])
                ->add('buyer_bank_ics', TextType::class, ['required' => false])
            ;
        
        /*$builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $property = $event->getData();
            $form = $event->getForm();
            $form->add('parking', IntegerType::class, ['required' => false]);
                    // check if the Product object is "new"
                    // If no data is passed to the form, the data is "null".
                    // This should be considered a new "Product"
                    if ($property->getIntitulesIndicesInitial()==1) {//urbain
                        $form->add('initialIndex', EntityType::class, [
                            'class' => RevaluationHistory::class,
                            'query_builder' => function (EntityRepository $er) {
                                return $er->createQueryBuilder('rh')
                                ->where('rh.type LIKE :key1')
                                ->setParameter('key1', "Urbains")
                                    ->orderBy('rh.id', 'DESC');
                            },
                            'choice_label' => function (RevaluationHistory $rh): string {
                                return $rh->getValue().' '.$rh->getType().' mois de '.strftime('%B %Y',$rh->getDate()->getTimestamp());
                            },
                        ]);
                    }else if ($property->getIntitulesIndicesInitial()==2) {//ménages
                        $form->add('initialIndex', EntityType::class, [
                            'class' => RevaluationHistory::class,
                            'query_builder' => function (EntityRepository $er) {
                                return $er->createQueryBuilder('rh')
                                ->where('rh.type LIKE :key2 ')
                                
                                ->setParameter('key2', "Ménages")
                                    ->orderBy('rh.id', 'DESC');
                            },
                            'choice_label' => function (RevaluationHistory $rh): string {
                                return $rh->getValue().' '.$rh->getType().' mois de '.strftime('%B %Y',$rh->getDate()->getTimestamp());
                            },
                        ]);
                    }
        });*/
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
        ]);
    }
}
