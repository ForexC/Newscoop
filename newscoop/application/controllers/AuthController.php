<?php
/**
 * @package Newscoop
 * @copyright 2011 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 */
class AuthController extends Zend_Controller_Action
{
    /** @var Zend_Auth */
    private $auth;

    public function init()
    {
        $this->_helper->layout->disableLayout();
        $this->auth = Zend_Auth::getInstance();
    }

    public function indexAction()
    {
        if ($this->auth->hasIdentity()) {
            $this->_helper->redirector('index', 'index');
        }

        $translator = Zend_Registry::get('container')->getService('translator');

        $form = new Application_Form_Login();

        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();
            $adapter = $this->_helper->service('auth.adapter');
            $adapter->setEmail($values['email'])->setPassword($values['password']);
            $result = $this->auth->authenticate($adapter);

            if ($result->getCode() == Zend_Auth_Result::SUCCESS) {
                $expire = null;
                if (!empty($values['remember_me'])) {
                    // set expire to 10 years in the future
                    $expire = time() + (10 * 365 * 24 * 60 * 60);
                }

                setcookie('NO_CACHE', '1', $expire, '/', '.'.$this->extractDomain($_SERVER['HTTP_HOST']));
                if (isset($values['_target_path'])) {
                    $this->_helper->redirector->gotoUrl($values['_target_path']);
                }

                $this->_helper->redirector('index', 'dashboard');
            } else {
                $form->addError($translator->trans("Invalid credentials"));
            }
        }

        $this->view->form = $form;
    }

    public function logoutAction()
    {
        if ($this->auth->hasIdentity()) {
            $this->auth->clearIdentity();
        }

        setcookie('NO_CACHE', 'NO', time()-3600, '/', '.'.$this->extractDomain($_SERVER['HTTP_HOST']));
        $url = $this->_getParam('url');
        if (!is_null($url)) {
            $this->_redirect($url);
        }

        $this->_helper->redirector->gotoUrl('?t='.time());
    }

    public function socialAction()
    {
        $preferencesService = \Zend_Registry::get('container')->getService('system_preferences_service');
        $userService = \Zend_Registry::get('container')->getService('user');
        $session = \Zend_Registry::get('container')->getService('session');

        $config = array(
            'base_url' => $this->view->serverUrl($this->view->url(array('action' => 'socialendpoint'))),
            'debug_mode' => false,
            'providers' => array(
                'Facebook' => array(
                    'enabled' => true,
                    'keys'    => array(
                        'id' => $preferencesService->facebook_appid,
                        'secret' => $preferencesService->facebook_appsecret,
                    ),
                ),
            ),
        );

        try {
            $hauth = new Hybrid_Auth($config);
            $adapter = $hauth->authenticate($this->_getParam('provider'));
            $userData = $adapter->getUserProfile();

            $socialAdapter = $this->_helper->service('auth.adapter.social');
            $socialAdapter->setProvider($adapter->id)->setProviderUserId($userData->identifier);
            $result = $this->auth->authenticate($socialAdapter);

            if ($result->getCode() !== Zend_Auth_Result::SUCCESS) {
                $user = $this->_helper->service('user')->findOneBy(array('email' => $userData->email));

                if (!$user) {
                    $publicationService = \Zend_Registry::get('container')->getService('newscoop_newscoop.publication_service');
                    $user = $this->_helper->service('user')->createPending($userData->email, $userData->firstName, $userData->lastName, null, $publicationService->getPublication()->getId());
                }

                $this->_helper->service('auth.adapter.social')->addIdentity($user, $adapter->id, $userData->identifier);
                $this->auth->authenticate($socialAdapter);
            } else {
                $user = $this->_helper->service('user')->getCurrentUser();
                $token = $userService->loginUser($user, 'frontend_area');
                $session->set('_security_frontend_area', serialize($token));
                $OAuthtoken = $userService->loginUser($user, 'oauth_authorize');
                $session->set('_security_oauth_authorize', serialize($OAuthtoken));
            }

            setcookie('NO_CACHE', '1', null, '/', '.'.$this->extractDomain($_SERVER['HTTP_HOST']));
            if ($user->isPending()) {
                $this->_forward('confirm', 'register', 'default', array(
                    'social' => true,
                ));
            } else {
                $request = $this->getRequest();
                if ($request->getParam('_target_path')) {
                    $this->_helper->redirector->gotoUrl($request->getParam('_target_path'));
                }

                $this->_helper->redirector('index', 'dashboard');
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function socialendpointAction()
    {
        Hybrid_Endpoint::process();
        exit;
    }

    public function passwordRestoreAction()
    {
        $form = new Application_Form_PasswordRestore();

        $translator = Zend_Registry::get('container')->getService('translator');
        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $user = $this->_helper->service('user')->findOneBy(array(
                'email' => $form->email->getValue(),
            ));

            if (!empty($user) && $user->isActive()) {
                $this->_helper->service('email')->sendPasswordRestoreToken($user);
                $this->_helper->flashMessenger($translator->trans("E-mail with instructions was sent to given email address."));
                $this->_helper->redirector('password-restore-after', 'auth');
            } elseif (empty($user)) {
                $form->email->addError($translator->trans("Given email not found."));
            }
        }

        $this->view->form = $form;
    }

    public function passwordRestoreAfterAction()
    {
    }

    public function passwordRestoreFinishAction()
    {
        $translator = Zend_Registry::get('container')->getService('translator');
        $user = $this->_helper->service('user')->find($this->_getParam('user'));
        if (empty($user)) {
            $this->_helper->flashMessenger(array('error', $translator->trans('User not found.')));
            $this->_helper->redirector('index', 'index', 'default');
        }

        if (!$user->isActive()) {
            $this->_helper->flashMessenger(array('error', $translator->trans('User is not active user.')));
            $this->_helper->redirector('index', 'index', 'default');
        }

        $token = $this->_getParam('token', false);
        if (!$token) {
            $this->_helper->flashMessenger(array('error', $translator->trans('No token provided.')));
            $this->_helper->redirector('index', 'index', 'default');
        }

        if (!$this->_helper->service('user.token')->checkToken($user, $token, 'password.restore')) {
            $this->_helper->flashMessenger(array('error', $translator->trans('Invalid token.')));
            $this->_helper->redirector('index', 'index', 'default');
        }

        $form = new Application_Form_PasswordRestorePassword();
        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $this->_helper->service('user')->save($form->getValues(), $user);
            $this->_helper->service('user.token')->invalidateTokens($user, 'password.restore');
            if (!$this->auth->hasIdentity()) { // log in
                $adapter = $this->_helper->service('auth.adapter');
                $adapter->setEmail($user->getEmail())->setPassword($form->password->getValue());
                $this->auth->authenticate($adapter);
                $this->_helper->redirector('index', 'dashboard');
            } else {
                $this->_helper->flashMessenger($translator->trans("Password changed"));
                $this->_helper->redirector('index', 'auth');
            }
        }

        $this->view->form = $form;
    }

    private function extractDomain($domain)
    {
        if (preg_match("/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i", $domain, $matches)) {
            return $matches['domain'];
        } else {
            return $domain;
        }
    }
}
