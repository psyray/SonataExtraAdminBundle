<?php

namespace Picoss\SonataExtraAdminBundle\Traits;

use Doctrine\Common\Collections\Criteria;
use Picoss\SonataExtraAdminBundle\Handler\SortableHandler;
use Picoss\SonataExtraAdminBundle\Model\TrashManager;
use Sonata\AdminBundle\Exception\ModelManagerException;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExtraAdminController
{
    /**
     * Move element.
     *
     * @param int      $id
     * @param int|null $childId
     * @param string   $position
     *
     * @return Response
     */
    public function moveAction($id, $childId = null, $position, SortableHandler $sortableHandler)
    {
        $objectId = null !== $childId ? $childId : $id;

        $object = $this->admin->getObject($objectId);

        $lastPosition = $sortableHandler->getLastPosition($object);
        $position = $sortableHandler->getPosition($object, $position, $lastPosition);

        $object->setPosition($position);
        $this->admin->update($object);

        if ($this->isXmlHttpRequest()) {
            return $this->renderJson([
                'result' => 'ok',
                'objectId' => $this->admin->getNormalizedIdentifier($object),
            ]);
        }
        $this->addFlash('sonata_flash_success', $this->get('translator')->trans('flash_position_updated_successfully', [], 'PicossSonataExtraAdminBundle'));

        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }

    /**
     * Revert hystory.
     *
     * @param int $id
     * @param int $revision
     *
     * @return RedirectResponse|Response
     */
    public function historyRevertAction(Request $request, $id, $revision)
    {
        $id = $request->get($this->admin->getIdParameter());
        $object = $this->admin->getObject($id);

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        if ('POST' == $request->getMethod()) {
            // check the csrf token
            $this->validateCsrfToken('sonata.history.revert');

            try {
                $manager = $this->get('sonata.admin.audit.manager');

                if (!$manager->hasReader($this->admin->getClass())) {
                    throw new NotFoundHttpException(sprintf('unable to find the audit reader for class : %s', $this->admin->getClass()));
                }

                $reader = $manager->getReader($this->admin->getClass());
                $reader->revert($object, $revision);

                if ($this->isXmlHttpRequest()) {
                    return $this->renderJson(['result' => 'ok']);
                }

                $this->addFlash('sonata_flash_success', $this->get('translator')->trans('flash_history_revert_successfull', [], 'PicossSonataExtraAdminBundle'));
            } catch (ModelManagerException $e) {
                if ($this->isXmlHttpRequest()) {
                    return $this->renderJson(['result' => 'error']);
                }

                $this->addFlash('sonata_flash_error', $this->get('translator')->trans('flash_history_revert_error', [], 'PicossSonataExtraAdminBundle'));
            }

            return new RedirectResponse($this->admin->generateUrl('list'));
        }

        return $this->renderWithExtraParams($this->admin->getTemplate('history_revert'), [
            'object' => $object,
            'revision' => $revision,
            'action' => 'revert',
            'csrf_token' => $this->getCsrfToken('sonata.history.revert'),
        ]);
    }

    /**
     * Return the Response object associated to the trash action.
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     *
     * @return Response
     */
    public function trashAction()
    {
        // Disable soft deleteable filter & enable soft deleteable trash filter
        $em = $this->getDoctrine()->getManager();
        $em->getFilters()->disable('softdeleteable');
        $em->getFilters()->enable('softdeleteabletrash');

        $this->admin->checkAccess('delete');

        $datagrid = $this->admin->getDatagrid();
        $formView = $datagrid->getForm()->createView();

        // set the theme for the current Admin Form
        $this->setFormTheme($formView, $this->admin->getFilterTheme());

        return $this->renderWithExtraParams($this->admin->getTemplate('trash'), [
            'action' => 'trash',
            'form' => $formView,
            'datagrid' => $datagrid,
            'csrf_token' => $this->getCsrfToken('sonata.batch'),
        ]);
    }

    /**
     * Untrash the given element.
     *
     * @param int $id
     *
     * @return RedirectResponse|Response
     */
    public function untrashAction(Request $request, $id, TrashManager $trashManager)
    {
        // Disable soft deleteable filter & enable soft deleteable trash filter
        $em = $this->getDoctrine()->getManager();
        $em->getFilters()->disable('softdeleteable');
        $em->getFilters()->enable('softdeleteabletrash');

        $id = $request->get($this->admin->getIdParameter());
        $object = $this->admin->getObject($id);

        $this->admin->checkAccess('delete', $object);

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        if ('POST' == $request->getMethod()) {
            // check the csrf token
            $this->validateCsrfToken('sonata.untrash');

            try {
                if (!$trashManager->hasReader($this->admin->getClass())) {
                    throw new NotFoundHttpException(sprintf('unable to find the trash reader for class : %s', $this->admin->getClass()));
                }

                $reader = $trashManager->getReader($this->admin->getClass());
                $reader->restore($object);

                if ($this->isXmlHttpRequest()) {
                    return $this->renderJson(['result' => 'ok']);
                }

                $this->addFlash('sonata_flash_success', $this->get('translator')->trans('flash_untrash_successful', [], 'PicossSonataExtraAdminBundle'));
            } catch (ModelManagerException $e) {
                if ($this->isXmlHttpRequest()) {
                    return $this->renderJson(['result' => 'error']);
                }

                $this->addFlash('sonata_flash_error', $this->get('translator')->trans('flash_untrash_error', [], 'PicossSonataExtraAdminBundle'));
            }

            return new RedirectResponse($this->admin->generateUrl('list'));
        }

        return $this->renderWithExtraParams($this->admin->getTemplate('untrash'), [
            'object' => $object,
            'action' => 'untrash',
            'csrf_token' => $this->getCsrfToken('sonata.untrash'),
        ]);
    }

    /**
     * Delete the given element.
     *
     * @param int $id
     *
     * @return RedirectResponse|Response
     */
    public function hardDeleteAction(Request $request, $id)
    {
        // Disable soft deleteable filter & enable soft deleteable trash filter
        $em = $this->getDoctrine()->getManager();
        $em->getFilters()->disable('softdeleteable');
        $em->getFilters()->enable('softdeleteabletrash');

        $id = $request->get($this->admin->getIdParameter());
        $object = $this->admin->getObject($id);

        $this->admin->checkAccess('delete', $object);

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        if ('POST' == $request->getMethod()) {
            // check the csrf token
            $this->validateCsrfToken('sonata.hard_delete');

            try {
                $object->setDeletedAt(new \DateTime());
                $em->remove($object);
                $em->flush($object);

                if ($this->isXmlHttpRequest()) {
                    return $this->renderJson(['result' => 'ok']);
                }

                $this->addFlash('sonata_flash_success', $this->get('translator')->trans('flash_hard_delete_successful', [], 'PicossSonataExtraAdminBundle'));
            } catch (ORMException $e) {
                if ($this->isXmlHttpRequest()) {
                    return $this->renderJson(['result' => 'error']);
                }

                $this->addFlash('sonata_flash_error', $this->get('translator')->trans('flash_hard_delete_error', [], 'PicossSonataExtraAdminBundle'));
            }

            return new RedirectResponse($this->admin->generateUrl('list'));
        }

        return $this->renderWithExtraParams($this->admin->getTemplate('hard_delete'), [
            'object' => $object,
            'action' => 'hard_delete',
            'csrf_token' => $this->getCsrfToken('sonata.hard_delete'),
        ]);
    }

    /**
     * Delete all the element of the current class.
     *
     * @return RedirectResponse|Response
     */
    public function hardDeleteAllAction(Request $request)
    {
        // Disable soft deleteable filter & enable soft deleteable trash filter
        $em = $this->getDoctrine()->getManager();
        $em->getFilters()->disable('softdeleteable');
        $em->getFilters()->enable('softdeleteabletrash');

        $this->admin->checkAccess('delete');

        // Get objects to delete
        $objectRepository = $em->getRepository($this->admin->getClass());
        $criteria = new Criteria();
        $criteria->where(Criteria::expr()->neq('deletedAt', null));
        $objectsToDelete = $objectRepository->matching($criteria);
        $count = count($objectsToDelete);
        if (0 == $count) {
            $this->addFlash('sonata_flash_error', $this->get('translator')->trans('flash_hard_delete_all_no_item', [], 'PicossSonataExtraAdminBundle'));

            return new RedirectResponse($this->admin->generateUrl('trash'));
        }

        if ('POST' == $request->getMethod()) {
            // check the csrf token
            $this->validateCsrfToken('sonata.hard_delete_all');

            foreach ($objectsToDelete as $object) {
                try {
                    $object->setDeletedAt(new \DateTime());
                    $em->remove($object);
                    $em->flush($object);
                } catch (ORMException $e) {
                    if ($this->isXmlHttpRequest()) {
                        return $this->renderJson(['result' => 'error']);
                    }

                    $this->addFlash('sonata_flash_error', $this->get('translator')->trans('flash_hard_delete_all_error', [], 'PicossSonataExtraAdminBundle'));
                }
            }

            if ($this->isXmlHttpRequest()) {
                return $this->renderJson(['result' => 'ok']);
            }

            $this->addFlash('sonata_flash_success', $this->get('translator')->trans('flash_hard_delete_all_successful', [], 'PicossSonataExtraAdminBundle'));

            return new RedirectResponse($this->admin->generateUrl('list'));
        }

        return $this->renderWithExtraParams($this->admin->getTemplate('hard_delete_all'), [
            'count' => $count,
            'action' => 'hard_delete_all',
            'csrf_token' => $this->getCsrfToken('sonata.hard_delete_all'),
        ]);
    }

    /**
     * Untrash all elements.
     *
     * @param int $id
     *
     * @return RedirectResponse|Response
     */
    public function untrashAllAction(Request $request, TrashManager $trashManager)
    {
        // Disable soft deleteable filter & enable soft deleteable trash filter
        $em = $this->getDoctrine()->getManager();
        $em->getFilters()->disable('softdeleteable');
        $em->getFilters()->enable('softdeleteabletrash');
        
        $id = $request->get($this->admin->getIdParameter());
        $object = $this->admin->getObject($id);
        
        $this->admin->checkAccess('delete');
        
        // Get objects to restore
        $objectRepository = $em->getRepository($this->admin->getClass());
        $criteria = new Criteria();
        $criteria->where(Criteria::expr()->neq('deletedAt', null));
        $objectsToRestore = $objectRepository->matching($criteria);
        $count = count($objectsToRestore);
        if (0 == $count) {
            $this->addFlash('sonata_flash_error', $this->get('translator')->trans('flash_untrash_all_no_item', [], 'PicossSonataExtraAdminBundle'));
            
            return new RedirectResponse($this->admin->generateUrl('trash'));
        }
        
        if ('POST' == $request->getMethod()) {
            // check the csrf token
            $this->validateCsrfToken('sonata.untrash_all');
            
            try {
                if (!$trashManager->hasReader($this->admin->getClass())) {
                    throw new NotFoundHttpException(sprintf('unable to find the trash reader for class : %s', $this->admin->getClass()));
                }
                
                $reader = $trashManager->getReader($this->admin->getClass());
                
                foreach ($objectsToRestore as $object) {
                    try {
                        $reader->restore($object);
                    } catch (\Exception $e) {
                        if ($this->isXmlHttpRequest()) {
                            return $this->renderJson(['result' => 'error']);
                        }
                        
                        $this->addFlash('sonata_flash_error', $this->get('translator')->trans('flash_untrash_all_error', [], 'PicossSonataExtraAdminBundle'));
                    }
                }
                
                if ($this->isXmlHttpRequest()) {
                    return $this->renderJson(['result' => 'ok']);
                }
                
                $this->addFlash('sonata_flash_success', $this->get('translator')->trans('flash_untrash_all_successful', [], 'PicossSonataExtraAdminBundle'));
            } catch (ModelManagerException $e) {
                if ($this->isXmlHttpRequest()) {
                    return $this->renderJson(['result' => 'error']);
                }
                
                $this->addFlash('sonata_flash_error', $this->get('translator')->trans('flash_untrash_all_error', [], 'PicossSonataExtraAdminBundle'));
            }
            
            return new RedirectResponse($this->admin->generateUrl('list'));
        }
        
        return $this->renderWithExtraParams($this->admin->getTemplate('untrash_all'), [
            'count' => $count,
            'action' => 'untrash_all',
            'csrf_token' => $this->getCsrfToken('sonata.untrash_all'),
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function setFormTheme(FormView $formView, $theme)
    {
        $twig = $this->get('twig');

        $twig->getRuntime(FormRenderer::class)->setTheme($formView, $theme);
    }
}
