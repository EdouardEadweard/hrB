<?php

namespace App\Form\Manager;

use App\Entity\LeaveRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class ApprovalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', ChoiceType::class, [
                'label' => 'Décision',
                'choices' => [
                    'Approuver la demande' => 'approved',
                    'Rejeter la demande' => 'rejected',
                    'Demander des modifications' => 'pending_modification'
                ],
                'expanded' => true,
                'multiple' => false,
                'attr' => [
                    'class' => 'form-check-input-group'
                ],
                'choice_attr' => [
                    'approved' => ['class' => 'form-check-input text-success'],
                    'rejected' => ['class' => 'form-check-input text-danger'],
                    'pending_modification' => ['class' => 'form-check-input text-warning']
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner une décision'
                    ]),
                    new Choice([
                        'choices' => ['approved', 'rejected', 'pending_modification'],
                        'message' => 'Décision invalide'
                    ])
                ]
            ])
            ->add('managerComment', TextareaType::class, [
                'label' => 'Commentaire du manager',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Ajoutez un commentaire expliquant votre décision (obligatoire)'
                ],
                'help' => 'Ce commentaire sera visible par l\'employé',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le commentaire du manager est obligatoire'
                    ]),
                    new Length([
                        'min' => 10,
                        'max' => 1000,
                        'minMessage' => 'Le commentaire doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le commentaire ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('modifiedStartDate', DateType::class, [
                'label' => 'Date de début modifiée',
                'required' => false,
                'widget' => 'single_text',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => date('Y-m-d')
                ],
                'help' => 'Uniquement si vous souhaitez proposer une modification de la date de début',
                'constraints' => [
                    new GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'La date de début ne peut pas être antérieure à aujourd\'hui'
                    ])
                ]
            ])
            ->add('modifiedEndDate', DateType::class, [
                'label' => 'Date de fin modifiée',
                'required' => false,
                'widget' => 'single_text',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => date('Y-m-d')
                ],
                'help' => 'Uniquement si vous souhaitez proposer une modification de la date de fin',
                'constraints' => [
                    new GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'La date de fin ne peut pas être antérieure à aujourd\'hui'
                    ])
                ]
            ])
            ->add('modifiedNumberOfDays', IntegerType::class, [
                'label' => 'Nombre de jours modifié',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'max' => 365
                ],
                'help' => 'Uniquement si vous souhaitez proposer une modification du nombre de jours',
                'constraints' => [
                    new Range([
                        'min' => 1,
                        'max' => 365,
                        'notInRangeMessage' => 'Le nombre de jours doit être entre {{ min }} et {{ max }}'  // <- Ajoutez cette ligne
                    ])
                ]
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Niveau de priorité',
                'required' => false,
                'mapped' => false,
                'choices' => [
                    'Normal' => 'normal',
                    'Urgent' => 'urgent',
                    'Critique' => 'critical'
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Définit la priorité de traitement de cette demande',
                'placeholder' => 'Sélectionner une priorité'
            ]);

        // Événement pour personnaliser le formulaire selon le statut
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();

            // Si c'est une demande existante, on peut ajouter des informations contextuelles
            if ($data instanceof LeaveRequest && $data->getId()) {
                // Ajouter des boutons d'action spécifiques
                $form->add('approve_submit', SubmitType::class, [
                    'label' => 'Approuver',
                    'attr' => [
                        'class' => 'btn btn-success me-2',
                        'onclick' => 'document.getElementById(\'approval_status_0\').checked = true;'
                    ]
                ])
                ->add('reject_submit', SubmitType::class, [
                    'label' => 'Rejeter',
                    'attr' => [
                        'class' => 'btn btn-danger me-2',
                        'onclick' => 'document.getElementById(\'approval_status_1\').checked = true;'
                    ]
                ])
                ->add('modify_submit', SubmitType::class, [
                    'label' => 'Demander modification',
                    'attr' => [
                        'class' => 'btn btn-warning me-2',
                        'onclick' => 'document.getElementById(\'approval_status_2\').checked = true;'
                    ]
                ]);
            }
        });

        // Bouton de soumission générique
        $builder->add('submit', SubmitType::class, [
            'label' => 'Valider la décision',
            'attr' => [
                'class' => 'btn btn-primary btn-lg'
            ]
        ]);

        // Événement pour validation conditionnelle
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $form->getData();

            // Si le statut est "pending_modification", au moins un champ de modification doit être rempli
            if ($data && $data->getStatus() === 'pending_modification') {
                $modifiedStartDate = $form->get('modifiedStartDate')->getData();
                $modifiedEndDate = $form->get('modifiedEndDate')->getData();
                $modifiedNumberOfDays = $form->get('modifiedNumberOfDays')->getData();

                if (!$modifiedStartDate && !$modifiedEndDate && !$modifiedNumberOfDays) {
                    $form->get('managerComment')->addError(new \Symfony\Component\Form\FormError(
                        'Si vous demandez une modification, veuillez préciser dans le commentaire quelles modifications sont souhaitées ou utiliser les champs de modification.'
                    ));
                }
            }

            // Validation des dates modifiées
            if ($form->has('modifiedStartDate') && $form->has('modifiedEndDate')) {
                $startDate = $form->get('modifiedStartDate')->getData();
                $endDate = $form->get('modifiedEndDate')->getData();

                if ($startDate && $endDate && $startDate > $endDate) {
                    $form->get('modifiedEndDate')->addError(new \Symfony\Component\Form\FormError(
                        'La date de fin doit être postérieure à la date de début'
                    ));
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LeaveRequest::class,
            'validation_groups' => ['Default', 'manager_approval'],
            'attr' => [
                'novalidate' => 'novalidate',
                'class' => 'needs-validation approval-form'
            ],
            'action_type' => null,  // <- Ajoutez cette ligne
        ]);

        // Définir les options autorisées
        $resolver->setAllowedTypes('action_type', ['string', 'null']);  // <- Ajoutez cette ligne
        $resolver->setAllowedValues('action_type', ['approve', 'reject', null]);  // <- Ajoutez cette ligne
    }

    public function getBlockPrefix(): string
    {
        return 'approval';
    }
}