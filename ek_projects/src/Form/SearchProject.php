<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\SearchProject
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\ek_projects\ProjectData;

/**
 * Provides a form for project search by keyword.
 */
class SearchProject extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_projects_search';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['p']['search'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#attributes' => array('placeholder' => $this->t('I.e. "123" or keyword'), 'class' => array()),
            '#required' => true,
            '#prefix' => '<div class="container-inline">',
        );


        $form['actions'] = array(
            '#type' => 'actions',
        );
        /* */
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Search'),
            '#suffix' => '</div>'
        );

        if ($form_state->get('message') != '') {
            $form['actions']['message'] = array(
                '#markup' => $form_state->get('message'),
            );
            $form_state->set('message', '');
            $form_state->setRebuild();
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $i = 0;
        $list = '<ul>';
        if (is_numeric($form_state->getValue('search'))) {
            $id1 = '%-' . trim($form_state->getValue('search')) . '%';
            $id2 = '%-' . trim($form_state->getValue('search')) . '-sub%';
            $a = array(':id1' => $id1, ':id2' => $id2);
            $query = 'SELECT id,pcode,pname from {ek_project} WHERE pcode like :id1 or id like :id2';
            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
        } else {
            $key = '%' . Xss::filter(trim($form_state->getValue('search'))) . '%';
            $a = array(':key' => $key);
            $query = 'SELECT id,pcode,pname FROM {ek_project} WHERE pname like :key';
            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);

            $query = 'SELECT p.id, p.pcode, pname FROM {ek_project} p '
                    . 'LEFT JOIN {ek_project_documents} d '
                    . 'ON p.pcode=d.pcode '
                    . 'WHERE filename like :id1';


            $id1 = '%' . trim($form_state->getValue('search')) . '%';
            $a = array(':id1' => $id1);
            $data2 = Database::getConnection('external_db', 'external_db')->query($query, $a);

            while ($d = $data2->fetchObject()) {
                $id = $d->id;
                $list .= '<li>[' . $this->t('document') . '] ' . $d->pname . ' - ' . ProjectData::geturl($id) . '</li>';
                $i++;
            }
        }


        while ($d = $data->fetchObject()) {
            $id = $d->id;
            $list .= '<li>' . $d->pname . ' - ' . ProjectData::geturl($id) . '</li>';
            $i++;
        }

        if ($i == 1) {
            $form_state->setRedirect('ek_projects_view', array('id' => $id));
        } else {
            $list .= '</ul>';
            $form_state->set('message', $list);
            $form_state->setRebuild();
        }




        //submitForm
    }

    //end class
}
