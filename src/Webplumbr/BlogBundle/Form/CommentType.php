<?php

namespace Webplumbr\BlogBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Range;

class CommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $data = isset($options['data'])
            ? $options['data']
            : array_fill_keys(
                array('id', 'commenter', 'content', 'post_id', 'parent_id', 'comment_id', 'status', 'comment_date', 'ip'),
                null
            );

        $builder
            ->add('commenter', 'text', array('label' => 'Commenter', 'attr' => array('value' => $data['commenter'])))
            ->add('content', 'textarea', array('label' => 'Content', 'attr' => array('value' => $data['content'], 'rows' => 10)))
            ->add('status', 'choice', array('choices' => $this->getCommentStatusChoices(), 'attr' => array('value' => $data['status'])))
            ->add('id', 'hidden', array('required' => false, 'data' => $data['id']))
            ->add('ip', 'hidden', array('required' => false, 'data' => $data['ip']))
            ->add('comment_id', 'hidden', array('required' => false, 'data' => $data['comment_id']))
            ->add('parent_id', 'hidden', array('required' => false, 'data' => $data['parent_id']))
            ->add('post_id', 'hidden', array('required' => false, 'data' => $data['post_id']))
            ->add('comment_date', 'hidden', array('required' => false, 'data' => $data['comment_date']))
            ->add('save', 'submit', array('label' => 'Save'));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $collectionConstraint = new Collection(array(
            'commenter' => array(
                new NotBlank(),
                new Length(array('min' => 2, 'max' => 128))
            ),
            'content' => array(
                new NotBlank()
            ),
            'status' => array(
                new NotBlank(),
                new Choice(array(
                    'choices' => $this->getCommentStatusKeys()
                ))
            ),
            'id' => array(
                new Length(array('min' => 0, 'max' => 40))
            ),
            'ip' => array(
                new Length(array('min' => 0, 'max' => 30))
            ),
            'comment_id' => array(
                new Range(array('min' => 0, 'max' => 10000000))
            ),
            'post_id' => array(
                new Range(array('min' => 0, 'max' => 10000000))
            ),
            'parent_id' => array(
                new Range(array('min' => 0, 'max' => 10000000))
            ),
            'comment_date' => array(
                new DateTime()
            )
        ));

        $resolver->setDefaults(
            array(
                'csrf_protection'  => false,
                'constraints'      => $collectionConstraint
            )
        );
    }

    public function getName()
    {
        return 'comment';
    }

    private function getCommentStatusKeys()
    {
        return array('approved', 'unapproved', 'spam');
    }

    private function getCommentStatusChoices()
    {
        return array_combine(
            $this->getCommentStatusKeys(),
            array('Approved', 'Unapproved', 'Spam')
        );
    }
}