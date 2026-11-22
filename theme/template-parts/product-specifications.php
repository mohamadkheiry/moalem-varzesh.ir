<?php defined('ABSPATH') || exit; ?>
<dl class="woocommerce-product-attributes mv-specs" aria-label="مشخصات کالا">
    <?php foreach ($args['rows'] as $row) : ?>
        <div class="mv-spec-row">
            <dt><?php echo wp_kses_post($row['label']); ?></dt>
            <dd>
                <?php if (!empty($row['groups'])) : ?>
                    <ul class="mv-spec-groups">
                        <?php foreach ($row['groups'] as $label => $items) : ?>
                            <li class="mv-spec-group">
                                <strong><?php echo esc_html($label); ?></strong>
                                <ul class="mv-spec-values">
                                    <?php foreach ($items as $item) : ?>
                                        <li><bdi><?php echo esc_html($item); ?></bdi></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif (!empty($row['options'])) : ?>
                    <ul class="mv-spec-values">
                        <?php foreach ($row['options'] as $option) : ?>
                            <li>
                                <?php if (!empty($row['links'][$option])) : ?>
                                    <a href="<?php echo esc_url($row['links'][$option]); ?>" rel="tag"><bdi><?php echo esc_html($option); ?></bdi></a>
                                <?php else : ?>
                                    <bdi><?php echo esc_html($option); ?></bdi>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <?php echo wp_kses_post($row['value'] ?? ''); ?>
                <?php endif; ?>
            </dd>
        </div>
    <?php endforeach; ?>
</dl>
