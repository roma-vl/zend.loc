<?php

declare(strict_types=1);

namespace Application\Controller\Admin\Controller;

use Exception;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator\Paginator;
use Laminas\View\Model\ViewModel;
use Application\Entity\Role;
use Application\Entity\User;
use Application\Form\Admin\EditUserForm;
use Application\Form\Admin\PasswordChangeForm;
use Application\Form\Admin\PasswordResetForm;
use Application\Form\Admin\AddUserForm;
use DoctrineORMModule\Paginator\Adapter\DoctrinePaginator as DoctrineAdapter;
use Doctrine\ORM\Tools\Pagination\Paginator as ORMPaginator;

/**
 *
 */
class UserController extends AbstractActionController
{
    private $entityManager;
    private $userManager;
    private $sessionContainer;
    private $reCaptchaManager;
    private $imageManager;

    public function __construct($entityManager,
                                $userManager,
                                $reCaptchaManager,
                                $sessionContainer,
                                $imageManager
    )
    {
        $this->entityManager    = $entityManager;
        $this->userManager      = $userManager;
        $this->sessionContainer = $sessionContainer;
        $this->reCaptchaManager = $reCaptchaManager;
        $this->imageManager     = $imageManager;
    }

    public function indexAction(): ViewModel
    {
        $page = $this->params()->fromQuery('page', 1);

        $query = $this->entityManager
            ->getRepository(User::class)
            ->findAllUsers();

//        $query = $this->entityManager
//            ->getRepository(User::class)
//            ->findUsersWhoCanPost();

        $adapter = new DoctrineAdapter(new ORMPaginator($query, false));
        $paginator = new Paginator($adapter);
        $paginator->setDefaultItemCountPerPage(5);
        $paginator->setCurrentPageNumber($page);

        $this->layout()->setTemplate('layout/users_layout');
        return new ViewModel([
            'users' => $paginator
        ]);
    }

    public function addAction()
    {
        $form = new AddUserForm($this->entityManager);
        $allRoles = $this->entityManager
            ->getRepository(Role::class)->findBy([], ['name'=>'ASC']);
        $roleList = [];
        foreach ($allRoles as $role) {
            $roleList[$role->getId()] = $role->getName();
        }

        $form->get('roles')->setValueOptions($roleList);

        if ($this->getRequest()->isPost()) {
            $data = $this->getRequestData();
            $form->setData($data);

            if ($form->isValid()) {
                $data = $form->getData();
                $data = $this->imageManager->uploadUserImage($data);
                $user = $this->userManager->addUser($data);
                $this->logger('info', 'Додано нового Коритстувача з Адмінки: '. $data['email']);
                return $this->redirect()->toRoute('users',
                    ['action' => 'view', 'id' => $user->getId()]);
            }
        }
        $this->layout()->setTemplate('layout/users_layout');
        return new ViewModel([
            'form' => $form
        ]);

    }

    public function viewAction()
    {
        $id = (int) $this->params()->fromRoute('id', -1);
        if ($id < 1) {
            $this->getResponse()->setStatusCode(404);
            return false;
        }

        $user = $this->entityManager->getRepository(User::class)->find($id);

        if ($user == null) {
            $this->getResponse()->setStatusCode(404);
            return false;
        }
        $this->layout()->setTemplate('layout/users_layout');
        return new ViewModel([
            'user' => $user
        ]);
    }

    public function editAction()
    {
        $id = (int)$this->params()->fromRoute('id', -1);
        if ($id < 1) {
            $this->getResponse()->setStatusCode(404);
            return false;
        }

        $user = $this->entityManager
            ->getRepository(User::class)->find($id);

        if ($user == null) {
            $this->getResponse()->setStatusCode(404);
            return false;
        }

        $form = new EditUserForm($this->entityManager, $user);

        $allRoles = $this->entityManager->getRepository(Role::class)
            ->findBy([], ['name'=>'ASC']);
        $roleList = [];
        foreach ($allRoles as $role) {
            $roleList[$role->getId()] = $role->getName();
        }

        $form->get('roles')->setValueOptions($roleList);


        if ($this->getRequest()->isPost()) {
            $data = $this->getRequestData();
            $form->setData($data);

            if ($form->isValid()) {
                $data = $form->getData();
                $data = $this->imageManager->uploadUserImage($data);
                $this->userManager->updateUser($user, $data);
                $this->logger('info', 'Внесено зміни з Адмінки для коритстувача : '. $data['email']);
                return $this->redirect()->toRoute('users',
                    ['action' => 'view', 'id' => $user->getId()]);
            }
        } else {

            $userRoleIds = [];
            foreach ($user->getRoles() as $role) {
                $userRoleIds[] = $role->getId();
            }

            $form->setData([
                'full_name' => $user->getFullName(),
                'email' => $user->getEmail(),
                'status' => $user->getStatus(),
                'roles' => $userRoleIds
            ]);
        }
        $this->layout()->setTemplate('layout/users_layout');
        return new ViewModel(array(
            'user' => $user,
            'form' => $form
        ));
    }

    public function emailConfirmationAction()
    {

        $email = $this->params()->fromQuery('email', null);
        $token = $this->params()->fromQuery('token', null);

        // Validate token length
        if ($token != null && (!is_string($token) || strlen($token) != 32)) {
            throw new Exception('Invalid token type or length');
        }
        try {
            $this->userManager->activateUser($email, $token);
            return $this->redirect()->toRoute('login');
        } catch (Exception $exception) {
            $this->flashMessenger()
                ->addErrorMessage('Виникла помилка : ' . $exception->getMessage());
            return $this->redirect()->toRoute('home');
        }


    }

    public function changePasswordAction()
    {
        $id = (int)$this->params()->fromRoute('id', -1);
        if ($id<1) {
            $this->getResponse()->setStatusCode(404);
            return false;
        }

        $user = $this->entityManager
            ->getRepository(User::class)->find($id);

        if ($user == null) {
            $this->getResponse()->setStatusCode(404);
            return false;
        }

        $form = new PasswordChangeForm('change');

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if($form->isValid()) {
                $data = $form->getData();
                if (!$this->userManager->changePassword($user, $data)) {
                    $this->flashMessenger()->addErrorMessage(
                        'Sorry, the old password is incorrect. Could not set the new password.');
                } else {
                    $this->flashMessenger()->addSuccessMessage('Changed the password successfully.');
                }

                // Redirect to "view" page
                return $this->redirect()->toRoute('users',
                    ['action'=>'view', 'id'=>$user->getId()]);
            }
        }
        $this->layout()->setTemplate('layout/users_layout');
        return new ViewModel([
            'user' => $user,
            'form' => $form
        ]);
    }

    public function resetPasswordAction()
    {
        $form = new PasswordResetForm();

        $recaptcha = $this->reCaptchaManager->init();

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            $result = $this->reCaptchaManager->checkReCaptcha($data['g-recaptcha-response']);

            if ($form->isValid() && true == $result) {
                $user = $this->entityManager
                    ->getRepository(User::class)->findOneByEmail($data['email']);

                if ($user != null && $user->getStatus() == User::STATUS_ACTIVE) {
                    $this->userManager->generatePasswordResetToken($user);
                    return $this->redirect()->toRoute('users',
                        ['action' => 'message', 'id' => 'sent']);
                } else {
                    return $this->redirect()->toRoute('users',
                        ['action' => 'message', 'id' => 'invalid-email']);
                }
            }
        }
        $this->layout()->setTemplate('layout/users_layout');
        return new ViewModel([
            'form' => $form,
            'recaptcha' => $recaptcha,
        ]);
    }

    public function setPasswordAction()
    {
        $email = $this->params()->fromQuery('email', null);
        $token = $this->params()->fromQuery('token', null);

        // Validate token length
        if ($token != null && (!is_string($token) || strlen($token) != 32)) {
            throw new Exception('Invalid token type or length');
        }

        if ($token === null || !$this->userManager->validatePasswordResetToken($email, $token)) {
            return $this->redirect()->toRoute('users',
                ['action' => 'message', 'id' => 'failed']);
        }

        $form = new PasswordChangeForm('reset');

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if ($form->isValid()) {
                $data = $form->getData();

                if ($this->userManager->setNewPasswordByToken($email, $token, $data['new_password'])) {
                    return $this->redirect()->toRoute('users',
                        ['action' => 'message', 'id' => 'set']);
                } else {
                    return $this->redirect()->toRoute('users',
                        ['action' => 'message', 'id' => 'failed']);
                }
            }
        }
        $this->layout()->setTemplate('layout/users_layout');
        return new ViewModel([
            'form' => $form
        ]);
    }

    public function messageAction(): ViewModel
    {
        $id = (string) $this->params()->fromRoute('id');

        // Validate input argument.
        if ($id != 'invalid-email' && $id != 'sent' && $id != 'set' && $id != 'failed') {
            throw new Exception('Invalid message ID specified');
        }
        $this->layout()->setTemplate('layout/users_layout');
        return new ViewModel([
            'id' => $id
        ]);
    }

    /**
     * @return array
     */
    private function getRequestData(): array
    {
        $request = $this->getRequest();
        return array_merge_recursive(
            $request->getPost()->toArray(),
            $request->getFiles()->toArray()
        );
    }

}
