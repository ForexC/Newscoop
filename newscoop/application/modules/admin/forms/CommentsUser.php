<?php

use Newscoop\Entity\User;

/**
 * User form
 */
class Admin_Form_CommentsUser extends Zend_Form
{
    public function init()
    {
        /*
        $user = new Zend_Form_Element_Select('user');
        $user->setLabel(getGS('Username'))
            ->setRequired(true)
            ->setOrder(30);
        */
        $user = new Zend_Form_Element_Text('user');
        $user->setLabel(getGS('User id'))
            ->setRequired(false)
            ->setOrder(30);
        $this->addElement($user);

        $this->addElement('text', 'name', array(
            'label' => getGS('Name'),
            'required' => false,
            'filters' => array(
                'stringTrim',
            ),
            'validators' => array(
                array('stringLength', false, array(1, 128)),
            ),
            'errorMessages' => array(getGS('Value is not $1 characters long', '1-128')),
            'order' => 40,
        ));

        $this->addElement('text', 'email', array(
            'label' => getGS('E-mail'),
            'required' => false,
            'order' => 50,
        ));

        $this->addElement('text', 'url', array(
            'label' => getGS('Website'),
            'required' => false,
            'order' => 60,
        ));

        $this->addDisplayGroup(array(
            'name',
            'email',
            'url'
        ), 'commentsUser_info', array(
            'legend' => getGS('Show comment user details'),
            'class' => 'toggle',
            'order' => 70,
        ));

        $this->addDisplayGroup(array(
            'user',
        ), 'commentsUser', array(
            'legend' => getGS('Show user'),
            'class' => 'toggle',
            'order' => 20,
        ));

        $this->addElement('submit', 'submit', array(
            'label' => getGS('Save'),
            'order' => 99,
        ));
    }

    /**
     * Set default values by entity
     *
     * @param Newscoop\Models\Comment\IUser $commentUser
     * @return void
     */
    public function setFromEntity($commentUser)
    {
        $this->setDefaults(array(
            'user' => $commentUser->getUserId(),
            'name' => $commentUser->getName(),
            'email' => $commentUser->getEmail(),
            'url'   => $commentUser->getUrl()
        ));

    }

}
