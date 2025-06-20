<?php

namespace App\Form\Manager;

use App\Entity\TeamMember;
use App\Entity\Team;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Doctrine\ORM\EntityRepository;

class TeamMemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentUser = $options['current_user'] ?? null;
        $isEdit = $options['is_edit'] ?? false;
        $managedTeams = $options['managed_teams'] ?? null; // Ajout de cette ligne

        $builder
            ->add('team', EntityType::class, [
                'class' => Team::class,
                'choice_label' => 'name',
                'label' => 'Équipe',
                'placeholder' => 'Sélectionner une équipe',
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function (EntityRepository $er) use ($currentUser, $managedTeams) {
                    $qb = $er->createQueryBuilder('t')
                        ->where('t.isActive = true')
                        ->orderBy('t.name', 'ASC');
                    
                    // Prioriser managed_teams si disponible, sinon utiliser currentUser
                    if ($managedTeams !== null && !empty($managedTeams)) {
                        $qb->andWhere('t.id IN (:managed_teams)')
                        ->setParameter('managed_teams', $managedTeams);
                    } elseif ($currentUser) {
                        $qb->andWhere('t.leader = :user OR t.department IN (
                            SELECT d FROM App\Entity\Department d WHERE d.manager = :user
                        )')
                        ->setParameter('user', $currentUser);
                    }
                    
                    return $qb;
                },
                'constraints' => [
                    new NotNull([
                        'message' => 'Veuillez sélectionner une équipe'
                    ])
                ],
                'help' => 'Sélectionnez l\'équipe à laquelle ajouter le membre'
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getFirstName() . ' ' . $user->getLastName() . ' (' . $user->getEmail() . ')';
                },
                'label' => 'Employé',
                'placeholder' => 'Sélectionner un employé',
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function (EntityRepository $er) use ($currentUser) {
                    $qb = $er->createQueryBuilder('u')
                        ->where('u.isActive = true')
                        ->orderBy('u.firstName', 'ASC')
                        ->addOrderBy('u.lastName', 'ASC');
                    
                    // Si un utilisateur courant est défini, filtrer les employés de son département
                    if ($currentUser) {
                        $qb->andWhere('u.department IN (
                            SELECT d FROM App\Entity\Department d WHERE d.manager = :user
                        ) OR u.manager = :user')
                        ->setParameter('user', $currentUser);
                    }
                    
                    return $qb;
                },
                'constraints' => [
                    new NotNull([
                        'message' => 'Veuillez sélectionner un employé'
                    ])
                ],
                'help' => 'Sélectionnez l\'employé à ajouter à l\'équipe'
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle dans l\'équipe',
                'choices' => [
                    'Membre' => 'member',
                    'Membre senior' => 'senior_member',
                    'Coordinateur' => 'coordinator',
                    'Responsable adjoint' => 'deputy_leader'
                ],
                'placeholder' => 'Sélectionner un rôle',
                'attr' => [
                    'class' => 'form-select'
                ],
                'mapped' => false,
                'required' => false,
                'help' => 'Définit le rôle et les responsabilités du membre dans l\'équipe',
                'constraints' => [
                    new Choice([
                        'choices' => ['member', 'senior_member', 'coordinator', 'deputy_leader'],
                        'message' => 'Rôle invalide'
                    ])
                ]
            ])
            ->add('joinedAt', DateTimeType::class, [
                'label' => 'Date d\'adhésion',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ],
                'data' => new \DateTime(),
                'constraints' => [
                    new NotNull([
                        'message' => 'La date d\'adhésion est obligatoire'
                    ]),
                    new LessThanOrEqual([
                        'value' => 'today',
                        'message' => 'La date d\'adhésion ne peut pas être dans le futur'
                    ])
                ],
                'help' => 'Date à laquelle le membre rejoint l\'équipe'
            ])
            ->add('leftAt', DateTimeType::class, [
                'label' => 'Date de sortie',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new GreaterThanOrEqual([
                        'propertyPath' => 'parent.all[joinedAt].data',
                        'message' => 'La date de sortie doit être postérieure à la date d\'adhésion'
                    ])
                ],
                'help' => 'Laissez vide si le membre est toujours dans l\'équipe'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Membre actif',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'data' => true,
                'help' => 'Décochez si le membre n\'est plus actif dans l\'équipe'
            ])
            ->add('responsibilities', TextareaType::class, [
                'label' => 'Responsabilités',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Décrivez les responsabilités spécifiques de ce membre dans l\'équipe'
                ],
                'constraints' => [
                    new Length([
                        'max' => 500,
                        'maxMessage' => 'Les responsabilités ne peuvent pas dépasser {{ limit }} caractères'
                    ])
                ],
                'help' => 'Description des tâches et responsabilités (optionnel)'
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes du manager',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Notes privées concernant ce membre d\'équipe'
                ],
                'constraints' => [
                    new Length([
                        'max' => 300,
                        'maxMessage' => 'Les notes ne peuvent pas dépasser {{ limit }} caractères'
                    ])
                ],
                'help' => 'Notes privées visibles uniquement par les managers'
            ]);

        // Événement pour personnaliser le formulaire
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($isEdit) {
            $form = $event->getForm();
            $data = $event->getData();

            // Si c'est une modification et que le membre a quitté l'équipe
            if ($isEdit && $data && $data->getLeftAt()) {
                $form->add('reactivate', CheckboxType::class, [
                    'label' => 'Réactiver le membre',
                    'required' => false,
                    'mapped' => false,
                    'attr' => [
                        'class' => 'form-check-input text-success'
                    ],
                    'help' => 'Cochez pour réintégrer ce membre dans l\'équipe'
                ]);
            }

            // Ajouter des actions spécifiques en mode édition
            if ($isEdit) {
                $form->add('transfer', ChoiceType::class, [
                    'label' => 'Transférer vers une autre équipe',
                    'required' => false,
                    'mapped' => false,
                    'choices' => [
                        'Aucun transfert' => '',
                        'Préparer le transfert' => 'prepare_transfer',
                        'Transfert immédiat' => 'immediate_transfer'
                    ],
                    'attr' => [
                        'class' => 'form-select'
                    ],
                    'help' => 'Options de transfert vers une autre équipe'
                ]);
            }
        });

        // Événement pour validation conditionnelle
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $form->getData();

            // Vérification que l'utilisateur n'est pas déjà membre actif de cette équipe
            if ($data && $data->getTeam() && $data->getUser()) {
                // Cette validation devrait idéalement être faite dans le contrôleur
                // car elle nécessite une requête en base de données
            }

            // Si leftAt est défini, isActive doit être false
            if ($data && $data->getLeftAt() && $data->getIsActive()) {
                $form->get('isActive')->addError(new \Symfony\Component\Form\FormError(
                    'Un membre avec une date de sortie ne peut pas être actif'
                ));
            }

            // Si reactivate est coché, effacer leftAt
            if ($form->has('reactivate') && $form->get('reactivate')->getData()) {
                $data->setLeftAt(null);
                $data->setIsActive(true);
            }
        });

        // Boutons de soumission
        if ($isEdit) {
            $builder
                ->add('update', SubmitType::class, [
                    'label' => 'Mettre à jour',
                    'attr' => [
                        'class' => 'btn btn-primary me-2'
                    ]
                ])
                ->add('remove', SubmitType::class, [
                    'label' => 'Retirer de l\'équipe',
                    'attr' => [
                        'class' => 'btn btn-warning me-2',
                        'onclick' => 'return confirm("Êtes-vous sûr de vouloir retirer ce membre de l\'équipe ?");'
                    ]
                ]);
        } else {
            $builder->add('submit', SubmitType::class, [
                'label' => 'Ajouter à l\'équipe',
                'attr' => [
                    'class' => 'btn btn-success btn-lg'
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TeamMember::class,
            'validation_groups' => ['Default', 'team_member'],
            'current_user' => null,
            'is_edit' => false,
            'managed_teams' => null,
            'attr' => [
                'novalidate' => 'novalidate',
                'class' => 'needs-validation team-member-form'
            ]
        ]);

        $resolver->setAllowedTypes('current_user', ['null', User::class]);
        $resolver->setAllowedTypes('is_edit', 'bool');
        $resolver->setAllowedTypes('managed_teams', ['null', 'array']); 
    }

    public function getBlockPrefix(): string
    {
        return 'team_member';
    }
}