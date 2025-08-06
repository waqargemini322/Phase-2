<?php
// Shortcodes for ProjectTheme Memberships

// Freelancer Membership Packages Shortcode
add_shortcode("pt_display_freelancer_mem_packs", "pt_display_freelancer_mem_packs_fnc");
function pt_display_freelancer_mem_packs_fnc() {
    ob_start();
    ?>
    <div class="membership-plans-container">
        <?php for ($i = 1; $i <= 6; $i++) {
            $plan_name = get_option("pt_freelancer_membership_name_" . $i);
            if (!empty($plan_name)) {
                $plan_cost = get_option("pt_freelancer_membership_cost_" . $i, 0);
                $plan_time = get_option("pt_freelancer_membership_time_" . $i, 0);
                $plan_bids = get_option("pt_freelancer_membership_bids_" . $i, 0);
                $plan_connects = get_option("pt_freelancer_membership_connects_" . $i, 0);
                $plan_zoom_invites = get_option("pt_freelancer_membership_zoom_invites_" . $i, 0);
                ?>
                <div class="membership-plan-card">
                    <h3><?php echo esc_html($plan_name); ?></h3>
                    <div class="price">
                        <?php echo projectTheme_get_show_price($plan_cost); ?>
                        <?php if ($plan_cost > 0): ?>
                            <span>/ <?php echo esc_html($plan_time); ?> <?php _e('month(s)', 'ProjectTheme'); ?></span>
                        <?php endif; ?>
                    </div>
                    <ul class="features">
                        <li><?php echo sprintf(__("%s Bids", "ProjectTheme"), esc_html($plan_bids)); ?></li>
                        <?php if ($plan_connects > 0): ?>
                            <li><?php echo sprintf(__("%s Connects Credits", "ProjectTheme"), esc_html($plan_connects)); ?></li>
                        <?php endif; ?>
                        <?php if ($plan_zoom_invites > 0): ?>
                            <li><?php echo sprintf(__("%s Zoom Invites", "ProjectTheme"), esc_html($plan_zoom_invites)); ?></li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($plan_cost == 0): ?>
                        <a href="<?php echo get_site_url() . "/?get_free_membership=" . esc_attr($i) . "&type=monthly"; ?>" class="btn btn-primary"><?php _e('Activate Free Plan', 'ProjectTheme'); ?></a>
                    <?php else: ?>
                        <a href="<?php echo get_site_url() . "/?p_action=purchase_membership_display&mem_type=service_provider&id=" . esc_attr($i) . "&plan_type=monthly"; ?>" class="btn btn-success"><?php _e('Purchase Now', 'ProjectTheme'); ?></a>
                    <?php endif; ?>
                </div>
            <?php }
        } ?>
    </div>
    <?php
    return ob_get_clean();
}

// Project Owner Membership Packages Shortcode
add_shortcode("pt_display_buyer_mem_packs", "pt_display_buyer_mem_packs_fnc");
function pt_display_buyer_mem_packs_fnc() {
    ob_start();
    ?>
    <div class="membership-plans-container">
        <?php for ($i = 1; $i <= 6; $i++) {
            $plan_name = get_option("pt_project_owner_membership_name_" . $i);
            if (!empty($plan_name)) {
                $plan_cost = get_option("pt_project_owner_membership_cost_" . $i, 0);
                $plan_time = get_option("pt_project_owner_membership_time_" . $i, 0);
                $plan_projects = get_option("pt_project_owner_membership_projects_" . $i, 0);
                $plan_connects = get_option("pt_project_owner_membership_connects_" . $i, 0);
                $plan_zoom_invites = get_option("pt_project_owner_membership_zoom_invites_" . $i, 0);
                ?>
                <div class="membership-plan-card">
                    <h3><?php echo esc_html($plan_name); ?></h3>
                    <div class="price">
                        <?php echo projectTheme_get_show_price($plan_cost); ?>
                        <?php if ($plan_cost > 0): ?>
                            <span>/ <?php echo esc_html($plan_time); ?> <?php _e('month(s)', 'ProjectTheme'); ?></span>
                        <?php endif; ?>
                    </div>
                    <ul class="features">
                        <li><?php echo sprintf(__("%s Projects", "ProjectTheme"), esc_html($plan_projects)); ?></li>
                        <?php if ($plan_connects > 0): ?>
                            <li><?php echo sprintf(__("%s Connects Credits", "ProjectTheme"), esc_html($plan_connects)); ?></li>
                        <?php endif; ?>
                        <?php if ($plan_zoom_invites > 0): ?>
                            <li><?php echo sprintf(__("%s Zoom Invites", "ProjectTheme"), esc_html($plan_zoom_invites)); ?></li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($plan_cost == 0): ?>
                        <a href="<?php echo get_site_url() . "/?get_free_membership=" . esc_attr($i) . "&type=monthly"; ?>" class="btn btn-primary"><?php _e('Activate Free Plan', 'ProjectTheme'); ?></a>
                    <?php else: ?>
                        <a href="<?php echo get_site_url() . "/?p_action=purchase_membership_display&mem_type=project_owner&id=" . esc_attr($i) . "&plan_type=monthly"; ?>" class="btn btn-success"><?php _e('Purchase Now', 'ProjectTheme'); ?></a>
                    <?php endif; ?>
                </div>
            <?php }
        } ?>
    </div>
    <?php
    return ob_get_clean();
}

// Investor Membership Packages Shortcode
add_shortcode("pt_display_investor_mem_packs", "pt_display_investor_mem_packs_fnc");
function pt_display_investor_mem_packs_fnc() {
    ob_start();
    ?>
    <div class="membership-plans-container">
        <?php
        // Fetch all investor plans (monthly and yearly)
        $investor_plans = [];
        for ($i = 1; $i <= 6; $i++) {
            // Monthly
            $plan_name_monthly = get_option("pt_investor_membership_name_" . $i);
            if (!empty($plan_name_monthly)) {
                $features_monthly = [];
                for ($f = 1; $f <= 5; $f++) {
                    $feature = get_option('pt_investor_membership_feature' . $f . '_' . $i);
                    if (!empty($feature)) {
                        $features_monthly[] = $feature;
                    }
                }
                $investor_plans[] = [
                    'id' => $i,
                    'name' => $plan_name_monthly,
                    'cost' => get_option("pt_investor_membership_cost_" . $i, 0),
                    'time' => get_option("pt_investor_membership_time_" . $i, 0),
                    'items' => get_option("pt_investor_membership_bids_" . $i, 0),
                    'connects' => get_option("pt_investor_membership_connects_" . $i, 0),
                    'zoom_invites' => get_option("pt_investor_membership_zoom_invites_" . $i, 0),
                    'features' => $features_monthly,
                    'type' => 'monthly'
                ];
            }

            // Yearly
            $plan_name_yearly = get_option("pt_investor_membership_name_yearly_" . $i);
            if (!empty($plan_name_yearly)) {
                $features_yearly = [];
                for ($f = 1; $f <= 5; $f++) {
                    $feature = get_option('pt_investor_membership_feature' . $f . '_yearly_' . $i);
                    if (!empty($feature)) {
                        $features_yearly[] = $feature;
                    }
                }
                $investor_plans[] = [
                    'id' => $i,
                    'name' => $plan_name_yearly,
                    'cost' => get_option("pt_investor_membership_cost_yearly_" . $i, 0),
                    'time' => get_option("pt_investor_membership_time_yearly_" . $i, 0),
                    'items' => get_option("pt_investor_membership_bids_yearly_" . $i, 0),
                    'connects' => get_option("pt_investor_membership_connects_yearly_" . $i, 0),
                    'zoom_invites' => get_option("pt_investor_membership_zoom_invites_yearly_" . $i, 0),
                    'features' => $features_yearly,
                    'type' => 'yearly'
                ];
            }
        }

        // Sort plans by cost (optional)
        usort($investor_plans, function($a, $b) {
            return $a['cost'] <=> $b['cost'];
        });

        foreach ($investor_plans as $plan) {
            $time_unit = ($plan['type'] == 'yearly') ? __('year(s)', 'ProjectTheme') : __('month(s)', 'ProjectTheme');
            ?>
            <div class="membership-plan-card">
                <h3><?php echo esc_html($plan['name']); ?></h3>
                <div class="price">
                    <?php echo projectTheme_get_show_price($plan['cost']); ?>
                    <?php if ($plan['cost'] > 0): ?>
                        <span>/ <?php echo esc_html($plan['time']); ?> <?php echo esc_html($time_unit); ?></span>
                    <?php endif; ?>
                </div>
                <ul class="features">
                    <li><?php echo sprintf(__("%s Bids", "ProjectTheme"), esc_html($plan['items'])); ?></li>
                    <?php if ($plan['connects'] > 0): ?>
                        <li><?php echo sprintf(__("%s Connects Credits", "ProjectTheme"), esc_html($plan['connects'])); ?></li>
                    <?php endif; ?>
                    <?php if ($plan['zoom_invites'] > 0): ?>
                        <li><?php echo sprintf(__("%s Zoom Invites", "ProjectTheme"), esc_html($plan['zoom_invites'])); ?></li>
                    <?php endif; ?>
                    <?php
                    if (!empty($plan['features'])) {
                        foreach ($plan['features'] as $feature) {
                            echo '<li>' . esc_html($feature) . '</li>';
                        }
                    }
                    ?>
                </ul>
                <?php if ($plan['cost'] == 0): ?>
                    <a href="<?php echo get_site_url() . "/?get_free_membership=" . esc_attr($plan['id']) . "&type=" . esc_attr($plan['type']); ?>" class="btn btn-primary"><?php _e('Activate Free Plan', 'ProjectTheme'); ?></a>
                <?php else: ?>
                    <a href="<?php echo get_site_url() . "/?p_action=purchase_membership_display&mem_type=investor&id=" . esc_attr($plan['id']) . "&plan_type=" . esc_attr($plan['type']); ?>" class="btn btn-success"><?php _e('Purchase Now', 'ProjectTheme'); ?></a>
                <?php endif; ?>
            </div>
        <?php } ?>
    </div>
    <?php
    return ob_get_clean();
}

// NOTE: projectTheme_get_currency() and projectTheme_get_show_price() are assumed to be
// defined in the theme's functions.php or another core plugin.
// Their definitions have been removed from here to prevent redeclaration errors.

?>
