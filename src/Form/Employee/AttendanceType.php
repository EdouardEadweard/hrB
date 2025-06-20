<?php

namespace App\Form\Employee;

use App\Entity\Attendance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class AttendanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('workDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de travail',
                'data' => new \DateTime(),
                'attr' => [
                    'class' => 'form-control',
                    'max' => date('Y-m-d'),
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Sélectionnez la date de travail à enregistrer'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'La date de travail est obligatoire.'
                    ]),
                    new Assert\LessThanOrEqual([
                        'value' => 'today',
                        'message' => 'La date de travail ne peut pas être future.'
                    ])
                ]
            ])
            ->add('checkIn', TimeType::class, [
                'widget' => 'single_text',
                'label' => 'Heure d\'arrivée',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '60',
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Heure d\'arrivée au bureau'
                ],
                'constraints' => [
                    new Assert\Time([
                        'message' => 'Veuillez saisir une heure valide.'
                    ])
                ]
            ])
            ->add('checkOut', TimeType::class, [
                'widget' => 'single_text',
                'label' => 'Heure de départ',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '60',
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Heure de départ du bureau'
                ],
                'constraints' => [
                    new Assert\Time([
                        'message' => 'Veuillez saisir une heure valide.'
                    ])
                ]
            ])
            ->add('workedHours', HiddenType::class, [
                'attr' => [
                    'id' => 'workedHours'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut de présence',
                    'choices' => [
                        'Présent' => 'présent',
                        'Absent' => 'absent',
                        'Retard' => 'retard',
                        'Départ anticipé' => 'départ_anticipé',
                        'Télétravail' => 'télétravail',
                        'Mission' => 'mission'
                    ],
                    'data' => 'présent',
                'attr' => [
                    'class' => 'form-select',
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Sélectionnez votre statut de présence',
                    'id' => 'statusSelect'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Veuillez sélectionner un statut.'
                    ])
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes / Commentaires',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'class' => 'form-control',
                    'placeholder' => 'Ajoutez des notes ou commentaires (missions externes, formations, etc.)',
                    'maxlength' => 300,
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Informations complémentaires sur votre journée de travail'
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 300,
                        'maxMessage' => 'Les notes ne peuvent pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            // Boutons d'actions rapides
            ->add('quickCheckIn', ButtonType::class, [
                'label' => 'Pointer l\'arrivée',
                'attr' => [
                    'class' => 'btn btn-success me-2',
                    'type' => 'button',
                    'onclick' => 'setCurrentTime("checkIn")',
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Utiliser l\'heure actuelle comme heure d\'arrivée'
                ]
            ])
            ->add('quickCheckOut', ButtonType::class, [
                'label' => 'Pointer le départ',
                'attr' => [
                    'class' => 'btn btn-warning me-2',
                    'type' => 'button',
                    'onclick' => 'setCurrentTime("checkOut")',
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Utiliser l\'heure actuelle comme heure de départ'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer la présence',
                'attr' => [
                    'class' => 'btn btn-primary btn-lg w-100 mt-3',
                    'data-bs-toggle' => 'tooltip',
                    'data-bs-placement' => 'top',
                    'title' => 'Sauvegarder les informations de présence'
                ]
            ]);

        // Événement pour calculer automatiquement les heures travaillées
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            
            if (isset($data['checkIn']) && isset($data['checkOut']) && 
                !empty($data['checkIn']) && !empty($data['checkOut'])) {
                
                try {
                    $checkIn = new \DateTime($data['checkIn']);
                    $checkOut = new \DateTime($data['checkOut']);
                    
                    // Validation que l'heure de sortie soit postérieure à l'heure d'entrée
                    if ($checkOut > $checkIn) {
                        $interval = $checkIn->diff($checkOut);
                        $workedHours = $interval->h + ($interval->i / 60);
                        $data['workedHours'] = round($workedHours, 2);
                        $event->setData($data);
                    }
                } catch (\Exception $e) {
                    // En cas d'erreur de parsing des heures, on laisse vide
                    $data['workedHours'] = 0;
                    $event->setData($data);
                }
            } else {
                // Si pas d'heures définies mais statut présent, on met 0
                $data['workedHours'] = $data['status'] === 'present' ? 0 : null;
                $event->setData($data);
            }
        });

        // Événement pour adapter les champs selon le statut
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $attendance = $event->getData();
            $form = $event->getForm();
            
            // Si c'est une modification et qu'il y a déjà des données
            if ($attendance && $attendance->getId()) {
                // Pré-remplir les heures si elles existent
                if ($attendance->getCheckIn()) {
                    $form->get('checkIn')->setData($attendance->getCheckIn());
                }
                if ($attendance->getCheckOut()) {
                    $form->get('checkOut')->setData($attendance->getCheckOut());
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Attendance::class,
            'attr' => [
                'class' => 'needs-validation',
                'novalidate' => true,
                'id' => 'attendanceForm'
            ]
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'attendance';
    }
}