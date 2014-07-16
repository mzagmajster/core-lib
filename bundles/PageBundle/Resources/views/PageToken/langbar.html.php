<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$count = count($pages);
?>

<div class="page-lang-bar">
    <?php foreach ($pages as $page): ?>
    <?php $active = ($app->getRequest()->getRequestUri() == $page['url']); ?>
    <span>
        <?php if (!$active): ?>
        <a href="<?php echo $page["url"]; ?>">
            <?php endif; ?>
        <?php echo $page["lang"]; ?>
        <?php if (!$active): ?>
        </a>
        <?php endif; ?>
    </span>
    <?php $count--; ?>
    <?php if ($count): ?>
    <span> | </span>
    <?php endif; ?>
    <?php endforeach; ?>
</div>