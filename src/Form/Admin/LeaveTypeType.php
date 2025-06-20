<?php

namespace App\Form\Admin;

use App\Entity\LeaveType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LeaveTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du type de congé',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Congés payés, RTT, Maladie...'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le nom du type de congé est obligatoire'
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            
            ->add('code', TextType::class, [
                'label' => 'Code type',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: CP, RTT, MAL...',
                    'style' => 'text-transform: uppercase;'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le code du type est obligatoire'
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 10,
                        'minMessage' => 'Le code doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le code ne peut pas dépasser {{ limit }} caractères'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[A-Z0-9_-]+$/',
                        'message' => 'Le code ne peut contenir que des lettres majuscules, chiffres, tirets et underscores'
                    ])
                ]
            ])
            
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Description détaillée du type de congé...'
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 500,
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            
            ->add('maxDaysPerYear', IntegerType::class, [
                'label' => 'Nombre maximum de jours par an',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 25',
                    'min' => 0,
                    'max' => 365
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le nombre maximum de jours est obligatoire'
                    ]),
                    new Assert\Range([
                        'min' => 0,
                        'max' => 365,
                        'notInRangeMessage' => 'Le nombre de jours doit être entre {{ min }} et {{ max }}'
                    ])
                ]
            ])
            
            ->add('requiresApproval', CheckboxType::class, [
                'label' => 'Approbation requise',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ],
                'help' => 'Cocher si ce type de congé nécessite une approbation managériale'
            ])
            
            ->add('isPaid', CheckboxType::class, [
                'label' => 'Congé payé',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ],
                'help' => 'Cocher si ce type de congé est rémunéré'
            ])
            
            ->add('color', ColorType::class, [
                'label' => 'Couleur d\'affichage',
                'attr' => [
                    'class' => 'form-control form-control-color',
                    'title' => 'Choisir une couleur pour l\'affichage dans les calendriers'
                ],
                'data' => $options['data']->getColor() ?: '#007bff',
                'help' => 'Couleur utilisée pour l\'affichage dans les calendriers et graphiques'
            ])
            
            ->add('isActive', CheckboxType::class, [
                'label' => 'Type actif',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ],
                'help' => 'Décocher pour désactiver temporairement ce type de congé',
                'data' => $options['data']->isIsActive() ?? true
            ])
            
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ])
            
            ->add('cancel', SubmitType::class, [
                'label' => 'Annuler',
                'attr' => [
                    'class' => 'btn btn-secondary',
                    'formnovalidate' => true
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LeaveType::class,
            'attr' => [
                'novalidate' => 'novalidate' // Désactive la validation HTML5 pour utiliser celle de Symfony
            ]
        ]);
    }
}