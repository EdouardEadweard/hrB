<?php

namespace App\Form\Employee;

use App\Entity\LeaveRequest;
use App\Entity\LeaveType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class LeaveRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('leaveType', EntityType::class, [
                'class' => LeaveType::class,
                'choice_label' => 'name',
                'placeholder' => 'Sélectionnez un type de congé',
                'label' => 'Type de congé',
                'attr' => [
                    'class' => 'form-select',
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Choisissez le type de congé souhaité'
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('lt')
                        ->where('lt.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('lt.name', 'ASC');
                },
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Veuillez sélectionner un type de congé.'
                    ])
                ]
            ])
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de début',
                'attr' => [
                    'class' => 'form-control',
                    'min' => date('Y-m-d'),
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Sélectionnez la date de début de votre congé'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'La date de début est obligatoire.'
                    ]),
                    new Assert\GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'La date de début ne peut pas être antérieure à aujourd\'hui.'
                    ])
                ]
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de fin',
                'attr' => [
                    'class' => 'form-control',
                    'min' => date('Y-m-d'),
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Sélectionnez la date de fin de votre congé'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'La date de fin est obligatoire.'
                    ]),
                    new Assert\GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'La date de fin ne peut pas être antérieure à aujourd\'hui.'
                    ])
                ]
            ])
            ->add('numberOfDays', HiddenType::class, [
                'attr' => [
                    'id' => 'numberOfDays'
                ]
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Motif de la demande',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'class' => 'form-control',
                    'placeholder' => 'Précisez le motif de votre demande de congé (optionnel)',
                    'maxlength' => 500,
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Vous pouvez ajouter des détails sur votre demande (500 caractères max)'
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 500,
                        'maxMessage' => 'Le motif ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            /*->add('status', ChoiceType::class, [
                'choices' => LeaveRequest::STATUSES,
                'data' => LeaveRequest::STATUS_PENDING,
                'attr' => ['readonly' => true, 'disabled' => true]
            ])*/

            // pour cacher le statut
            ->add('status', HiddenType::class, [
                'data' => LeaveRequest::STATUS_PENDING
            ])

            ->add('submit', SubmitType::class, [
                'label' => 'Soumettre la demande',
                'attr' => [
                    'class' => 'btn btn-primary btn-lg w-100 mt-3',
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Cliquez pour envoyer votre demande de congé'
                ]
            ]);

        // Événement pour calculer automatiquement le nombre de jours
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            
            if (isset($data['startDate']) && isset($data['endDate'])) {
                $startDate = new \DateTime($data['startDate']);
                $endDate = new \DateTime($data['endDate']);
                
                // Validation que la date de fin soit postérieure à la date de début
                if ($endDate >= $startDate) {
                    // Calcul du nombre de jours ouvrables (exclut les weekends)
                    $numberOfDays = $this->calculateWorkingDays($startDate, $endDate);
                    $data['numberOfDays'] = $numberOfDays;
                    $event->setData($data);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LeaveRequest::class,
            'attr' => [
                'class' => 'needs-validation',
                'novalidate' => true,
                'id' => 'leaveRequestForm'
            ]
        ]);
    }

    /**
     * Calcule le nombre de jours ouvrables entre deux dates
     * (exclut les samedis et dimanches)
     */
    private function calculateWorkingDays(\DateTime $startDate, \DateTime $endDate): int
    {
        $workingDays = 0;
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            // Vérifie si c'est un jour ouvrable (lundi à vendredi)
            $dayOfWeek = $currentDate->format('N');
            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                $workingDays++;
            }
            $currentDate->add(new \DateInterval('P1D'));
        }

        return $workingDays;
    }
}