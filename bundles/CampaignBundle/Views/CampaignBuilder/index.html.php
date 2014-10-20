<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'campaign');

$header = ($entity->getId()) ?
    $view['translator']->trans('mautic.campaign.menu.edit',
        array('%name%' => $view['translator']->trans($entity->getName()))) :
    $view['translator']->trans('mautic.campaign.menu.new');
$view['slots']->set("headerTitle", $header);
?>

<?php echo $view['slots']->start('actions'); ?>

<?php echo $view['slots']->stop('actions'); ?>

<?php echo $view['form']->start($form); ?>
<!-- start: box layout -->
<div class="box-layout">
    <!-- container -->
    <div class="col-md-9 bg-auto height-auto bdr-r">
        <div class="pa-md">
            <?php
            echo $view['form']->row($form['name']);
            echo $view['form']->row($form['description']);
            ?>
        </div>
    </div>
    <div class="col-md-3 bg-white height-auto">
        <div class="pr-lg pl-lg pt-md pb-md">
            <?php
            echo $view['form']->row($form['category_lookup']);
            echo $view['form']->row($form['category']);
            echo $view['form']->row($form['isPublished']);
            echo $view['form']->row($form['publishUp']);
            echo $view['form']->row($form['publishDown']);
            ?>
        </div>
    </div>
</div>

    <?php echo $view['form']->end($form); ?>
    <?php
    $view['slots']->append('modal', $this->render('MauticCoreBundle:Helper:modal.html.php', array(
        'id'     => 'campaignEventModal',
        'header' => $view['translator']->trans('mautic.campaign.form.modalheader'),
    )));
    ?>
</div>