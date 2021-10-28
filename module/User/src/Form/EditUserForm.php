<?php

namespace User\Form;

use Laminas\Filter\ToInt;
use Laminas\Form\Form;
use Laminas\InputFilter\ArrayInput;
use Laminas\Validator\GreaterThan;
use Laminas\Validator\Hostname;
use User\Validator\UserExistsValidator;

class EditUserForm extends Form
{

    private $entityManager;

    private $user;

    public function __construct($entityManager = null, $user = null)
    {
        parent::__construct('edit-user-form');
        $this->setAttribute('method', 'post');
        $this->entityManager = $entityManager;
        $this->user = $user;

        $this->addElements();
        $this->addInputFilter();
    }

    /**
     * This method adds elements to form (input fields and submit button).
     */
    protected function addElements()
    {
        $this->add([
            'type' => 'text',
            'name' => 'email',
            'options' => [
                'label' => 'E-mail',
            ],
        ]);

        $this->add([
            'type' => 'text',
            'name' => 'full_name',
            'options' => [
                'label' => 'Full Name',
            ],
        ]);

        $this->add([
            'type' => 'select',
            'name' => 'status',
            'options' => [
                'label' => 'Status',
                'value_options' => [
                    1 => 'Active',
                    2 => 'Retired',
                ]
            ],
        ]);

        // Add "roles" field
        $this->add([
            'type'  => 'select',
            'name' => 'roles',
            'attributes' => [
                'multiple' => 'multiple',
            ],
            'options' => [
                'label' => 'Role(s)',
            ],
        ]);

        //@TODO Не генерує csrf токен
//        $this->add([
//            'type' => 'csrf',
//            'name' => 'csrf',
//            'options' => [
//                'csrf_options' => [
//                    'timeout' => 600
//                ]
//            ],
//        ]);

        $this->add([
            'type' => 'submit',
            'name' => 'submit',
            'attributes' => [
                'value' => 'Create'
            ],
        ]);
    }

    /**
     * This method creates input filter (used for form filtering/validation).
     */
    private function addInputFilter()
    {
        $inputFilter = $this->getInputFilter();

        $inputFilter->add([
            'name' => 'email',
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => 'StringLength',
                    'options' => [
                        'min' => 1,
                        'max' => 128
                    ],
                ],
                [
                    'name' => 'EmailAddress',
                    'options' => [
                        'allow' => Hostname::ALLOW_DNS,
                        'useMxCheck' => false,
                    ],
                ],
                [
                    'name' => UserExistsValidator::class,
                    'options' => [
                        'entityManager' => $this->entityManager,
                        'user' => $this->user
                    ],
                ],
            ],
        ]);

        $inputFilter->add([
            'name' => 'full_name',
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => 'StringLength',
                    'options' => [
                        'min' => 1,
                        'max' => 512
                    ],
                ],
            ],
        ]);

        $inputFilter->add([
            'name'       => 'status',
            'required'   => true,
            'filters'    => [
                ['name' => ToInt::class],
            ],
            'validators' => [
                [
                    'name'    => 'InArray',
                    'options' => ['haystack' => [1, 2]]
                ]
            ],
        ]);

        // Add input for "roles" field
        $inputFilter->add([
            'class'    => ArrayInput::class,
            'name'     => 'roles',
            'required' => true,
            'filters'  => [
                ['name' => 'ToInt'],
            ],
            'validators' => [
                [
                    'name'    => GreaterThan::class,
                    'options' => ['min' => 0]
                ],
            ],
        ]);
    }
}