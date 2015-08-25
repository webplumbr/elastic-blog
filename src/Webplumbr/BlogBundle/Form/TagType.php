<?php

namespace Webplumbr\BlogBundle\Form;

use Symfony\Component\Form\AbstractType;

class TagType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            //->add()
            ->add('save', 'submit', array('label' => 'Submit'));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $collectionConstraint = new Collection(array());

        $resolver->setDefaults(
            array(
                'csrf_protection' => false,
                'constraints'     => $collectionConstraint
            )
        );
    }

    public function getName()
    {
        return 'tag';
    }
}