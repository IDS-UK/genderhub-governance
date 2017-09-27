<div class="GenderHubTopBar top-bar-container contain-to-grid">
    <nav class="top-bar" data-topbar="">
        <ul class="title-area">
            <li class="name">
                <h1 class="GenderHubBranding">
                    <a href="http://www.genderhub.org">Gender Hub</a><span>e-learning</span>
                </h1>
            </li>
        </ul>
    </nav>
</div>

<div class="top-bar-container contain-to-grid">
    <nav class="top-bar" data-topbar role="navigation">
        <ul class="title-area">
            <li class="name">
                <h1><a href="<?php echo home_url(); ?>">Home</a></h1><!-- <?php bloginfo('name'); ?> -->
            </li>
            <li class="toggle-topbar menu-icon"><a href="#"><span></span></a></li>
        </ul>
        <section class="top-bar-section">
            <?php echo render_user_menu(); ?>
        </section>
        <section class="top-bar-section">
            <?php echo render_site_menu(); ?>
        </section>
    </nav>
</div>