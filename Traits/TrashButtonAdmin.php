<?php

namespace Picoss\SonataExtraAdminBundle\Traits;

trait TrashButtonAdmin
{
    /**
     * {@inheritdoc}
     */
    public function configureActionButtons($action, $object = null)
    {
        $list = parent::configureActionButtons($action, $object);

        if (in_array($action, [
            'list',
        ]) && $this->hasRoute('trash')) {
            $list['trash'] = [
                'template' => $this->getTemplate('button_trash'),
            ];
        }

        if (in_array($action, [
            'trash',
        ]) && $this->hasRoute('hard_delete_all')) {
            $list['untrash_all'] = [
                'template' => $this->getTemplate('button_untrash_all'),
            ];
        }
        
        if (in_array($action, [
            'trash',
        ]) && $this->hasRoute('hard_delete_all')) {
            $list['hard_delete_all'] = [
                'template' => $this->getTemplate('button_hard_delete_all'),
            ];
        }

        if (in_array($action, [
            'trash',
        ]) && $this->hasRoute('list')) {
            $list['list'] = [
                'template' => $this->getTemplate('button_list'),
            ];
        }

        return $list;
    }
}
