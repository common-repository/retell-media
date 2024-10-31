<?php

if (!defined('ABSPATH')) {
    exit;
}

function retell_render_settings_page()
{
    $options = get_option('retell_settings');

    if (!isset($options['retell_api_key']) || !is_array($options)) {
        $options = [];
        $options['retell_api_key'] = '';
    }
    ?>
    <div class="retell-body">
    <header class="retell-container retell-header">
        <a href="https://retell.media/">
<img src="<?php echo esc_url(plugins_url('/img/logo.svg', __FILE__)); ?>" alt="Retell">
        </a>
    </header>

    <section class="retell-container">
        <div class="retell-hero">
            <h1 class="retell-title">Retell.media</h1>

            <p class="retell-description">Is a platform based on artificial intelligence technologies designed for monitoring news events as well as for creating high-quality content</p>

            <a href="https://retell.media/?utm_source=plugin&utm_medium=settings_page" target="_blank" class="retell-button">To the platform</a>
        </div>
    </section>

    <section class="retell-container retell-how">
        <h3 class="retell-title">How it works?</h3>

        <div class="retell-steps">
            <div class="retell-step">
                <h3 class="retell-title">Find content</h3>

                <p class="retell-description">Define keywords, specify news sources, and receive a flow of matching articles within minutes</p>
            </div>

            <div class="retell-step">
                <h3 class="retell-title">Rewrite content</h3>

                <p class="retell-description">Adopt, change styles, translate to different languages - in a few clicks</p>
            </div>

            <div class="retell-step">
                <h3 class="retell-title">Create digest</h3>

                <p class="retell-description">Summarize multiple articles at once, creating a new story</p>
            </div>

            <div class="retell-step">
                <h3 class="retell-title">Generate articles</h3>

                <p class="retell-description">Generate articles from scratch, using prompt and quotes</p>
            </div>
        </div>
    </section>

    <section class="retell-container retell-plugin">
        <h3 class="retell-title">Our plugin</h3>

        <p class="retell-description">Our plugin is a convenient tool that enables content transfer directly from your account on the retell.media platform to your WordPress website.</p>

        <div class="retell-steps">
            <div class="retell-step">
                <h3 class="retell-title">01</h3>

                <p class="retell-description">You can configure the frequency and number of publications you want to receive, just specify the desired parameters in the corresponding fields, generate a unique API key, and insert it into the appropriate field.</p>
            </div>

            <div class="retell-step">
                <h3 class="retell-title">02</h3>

                <p class="retell-description">After that, save the settings, and your content will be automatically transferred to your website.</p>
            </div>
        </div>
    </section>

    <section class="retell-container retell-key-container">
        <div class="retell-key">
            <h3 class="retell-title">API Key</h3>

            <p class="retell-description">Paste your API key from retell.media into this field</p>

    <form class="retell-form" method="post" action="options.php">
    <?php settings_fields('retell_settings'); ?>
    <label class="retell-label">

                    <input type="text" placeholder="Your API Key" class="retell-input" name='retell_settings[retell_api_key]' value='<?php echo esc_textarea($options['retell_api_key']); ?>'>
                </label>

                <button class="retell-button" type="submit">Save</button>
            </form>

            <a class="retell-support" href="mailto:support@retell.media">support@retell.media</a>
        </div>
    </section>
    </div>
    <?php
}
