<?php
// templates/privacy-policy-template.php

/**
 * SPIRAL Engine Privacy Policy Template
 * 
 * Creates privacy policy from Security Command Center requirements:
 * - GDPR-compliant template
 * - All required disclosures
 * - User rights
 * - Contact information
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get site information
$site_name = get_bloginfo('name');
$site_url = home_url();
$admin_email = get_option('admin_email');
$last_updated = get_option('spiralengine_privacy_policy_updated', current_time('mysql'));
?>

<div class="spiralengine-privacy-policy">
    <h1><?php _e('Privacy Policy', 'spiral-engine'); ?></h1>
    
    <p class="policy-updated">
        <strong><?php _e('Last Updated:', 'spiral-engine'); ?></strong> 
        <?php echo date_i18n(get_option('date_format'), strtotime($last_updated)); ?>
    </p>
    
    <div class="policy-toc">
        <h2><?php _e('Table of Contents', 'spiral-engine'); ?></h2>
        <ol>
            <li><a href="#introduction"><?php _e('Introduction', 'spiral-engine'); ?></a></li>
            <li><a href="#data-collection"><?php _e('Data We Collect', 'spiral-engine'); ?></a></li>
            <li><a href="#how-we-use"><?php _e('How We Use Your Data', 'spiral-engine'); ?></a></li>
            <li><a href="#legal-basis"><?php _e('Legal Basis for Processing', 'spiral-engine'); ?></a></li>
            <li><a href="#data-sharing"><?php _e('Data Sharing', 'spiral-engine'); ?></a></li>
            <li><a href="#data-security"><?php _e('Data Security', 'spiral-engine'); ?></a></li>
            <li><a href="#data-retention"><?php _e('Data Retention', 'spiral-engine'); ?></a></li>
            <li><a href="#your-rights"><?php _e('Your Rights', 'spiral-engine'); ?></a></li>
            <li><a href="#special-categories"><?php _e('Special Categories of Data', 'spiral-engine'); ?></a></li>
            <li><a href="#cookies"><?php _e('Cookies', 'spiral-engine'); ?></a></li>
            <li><a href="#international"><?php _e('International Transfers', 'spiral-engine'); ?></a></li>
            <li><a href="#children"><?php _e('Children\'s Privacy', 'spiral-engine'); ?></a></li>
            <li><a href="#changes"><?php _e('Changes to This Policy', 'spiral-engine'); ?></a></li>
            <li><a href="#contact"><?php _e('Contact Us', 'spiral-engine'); ?></a></li>
        </ol>
    </div>
    
    <section id="introduction">
        <h2><?php _e('1. Introduction', 'spiral-engine'); ?></h2>
        <p>
            <?php printf(
                __('Welcome to %s. We are committed to protecting your personal data and respecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our SPIRAL Engine wellness platform.', 'spiral-engine'),
                esc_html($site_name)
            ); ?>
        </p>
        <p>
            <?php _e('By using our services, you agree to the collection and use of information in accordance with this policy. If you do not agree with our policies and practices, please do not use our services.', 'spiral-engine'); ?>
        </p>
    </section>
    
    <section id="data-collection">
        <h2><?php _e('2. Data We Collect', 'spiral-engine'); ?></h2>
        
        <h3><?php _e('2.1 Information You Provide to Us', 'spiral-engine'); ?></h3>
        <ul>
            <li><strong><?php _e('Account Information:', 'spiral-engine'); ?></strong> <?php _e('Username, email address, password (encrypted), display name', 'spiral-engine'); ?></li>
            <li><strong><?php _e('Profile Information:', 'spiral-engine'); ?></strong> <?php _e('Biological sex (optional), timezone, preferred language', 'spiral-engine'); ?></li>
            <li><strong><?php _e('Health-Related Data:', 'spiral-engine'); ?></strong> <?php _e('SPIRAL assessment responses, wellness tracking data, menstrual cycle data (if opted in)', 'spiral-engine'); ?></li>
            <li><strong><?php _e('Communication Data:', 'spiral-engine'); ?></strong> <?php _e('Messages you send to us, support requests, feedback', 'spiral-engine'); ?></li>
            <li><strong><?php _e('Payment Information:', 'spiral-engine'); ?></strong> <?php _e('Processed securely by our payment processors (we do not store credit card details)', 'spiral-engine'); ?></li>
        </ul>
        
        <h3><?php _e('2.2 Information Collected Automatically', 'spiral-engine'); ?></h3>
        <ul>
            <li><strong><?php _e('Usage Data:', 'spiral-engine'); ?></strong> <?php _e('Pages visited, features used, time spent on platform', 'spiral-engine'); ?></li>
            <li><strong><?php _e('Device Information:', 'spiral-engine'); ?></strong> <?php _e('IP address (may be anonymized), browser type, device type, operating system', 'spiral-engine'); ?></li>
            <li><strong><?php _e('Cookies:', 'spiral-engine'); ?></strong> <?php _e('Session cookies, preference cookies, analytics cookies (with consent)', 'spiral-engine'); ?></li>
            <li><strong><?php _e('Log Data:', 'spiral-engine'); ?></strong> <?php _e('Server logs, error reports, performance data', 'spiral-engine'); ?></li>
        </ul>
        
        <h3><?php _e('2.3 Special Categories of Personal Data', 'spiral-engine'); ?></h3>
        <p>
            <?php _e('We process special categories of personal data (health data) only with your explicit consent. This includes:', 'spiral-engine'); ?>
        </p>
        <ul>
            <li><?php _e('Mental wellness assessment scores', 'spiral-engine'); ?></li>
            <li><?php _e('Mood and symptom tracking data', 'spiral-engine'); ?></li>
            <li><?php _e('Crisis intervention records', 'spiral-engine'); ?></li>
            <li><?php _e('Biological cycle data (if you opt in)', 'spiral-engine'); ?></li>
        </ul>
    </section>
    
    <section id="how-we-use">
        <h2><?php _e('3. How We Use Your Data', 'spiral-engine'); ?></h2>
        
        <h3><?php _e('3.1 Primary Purposes', 'spiral-engine'); ?></h3>
        <ul>
            <li><?php _e('Provide and maintain our wellness tracking services', 'spiral-engine'); ?></li>
            <li><?php _e('Personalize your experience and provide tailored insights', 'spiral-engine'); ?></li>
            <li><?php _e('Process your SPIRAL assessments and track your progress', 'spiral-engine'); ?></li>
            <li><?php _e('Send you important notifications about your account', 'spiral-engine'); ?></li>
            <li><?php _e('Provide customer support and respond to your requests', 'spiral-engine'); ?></li>
            <li><?php _e('Ensure platform security and prevent fraud', 'spiral-engine'); ?></li>
        </ul>
        
        <h3><?php _e('3.2 With Your Consent', 'spiral-engine'); ?></h3>
        <ul>
            <li><?php _e('Send you wellness tips and educational content', 'spiral-engine'); ?></li>
            <li><?php _e('Conduct research to improve our services (anonymized data)', 'spiral-engine'); ?></li>
            <li><?php _e('Share insights with your designated healthcare providers', 'spiral-engine'); ?></li>
            <li><?php _e('Provide crisis intervention resources when needed', 'spiral-engine'); ?></li>
        </ul>
    </section>
    
    <section id="legal-basis">
        <h2><?php _e('4. Legal Basis for Processing', 'spiral-engine'); ?></h2>
        <p><?php _e('We process your personal data under the following legal bases:', 'spiral-engine'); ?></p>
        
        <table class="legal-basis-table">
            <thead>
                <tr>
                    <th><?php _e('Purpose', 'spiral-engine'); ?></th>
                    <th><?php _e('Legal Basis', 'spiral-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('Account creation and management', 'spiral-engine'); ?></td>
                    <td><?php _e('Contract performance', 'spiral-engine'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Health data processing', 'spiral-engine'); ?></td>
                    <td><?php _e('Explicit consent', 'spiral-engine'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Marketing communications', 'spiral-engine'); ?></td>
                    <td><?php _e('Consent', 'spiral-engine'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Security and fraud prevention', 'spiral-engine'); ?></td>
                    <td><?php _e('Legitimate interests', 'spiral-engine'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Legal compliance', 'spiral-engine'); ?></td>
                    <td><?php _e('Legal obligation', 'spiral-engine'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Crisis intervention', 'spiral-engine'); ?></td>
                    <td><?php _e('Vital interests', 'spiral-engine'); ?></td>
                </tr>
            </tbody>
        </table>
    </section>
    
    <section id="data-sharing">
        <h2><?php _e('5. Data Sharing', 'spiral-engine'); ?></h2>
        <p><?php _e('We do not sell your personal data. We may share your information only in the following circumstances:', 'spiral-engine'); ?></p>
        
        <h3><?php _e('5.1 Service Providers', 'spiral-engine'); ?></h3>
        <ul>
            <li><?php _e('Payment processors (for membership payments)', 'spiral-engine'); ?></li>
            <li><?php _e('Email service providers (for transactional emails)', 'spiral-engine'); ?></li>
            <li><?php _e('Cloud hosting providers (for secure data storage)', 'spiral-engine'); ?></li>
            <li><?php _e('Analytics providers (anonymized data only)', 'spiral-engine'); ?></li>
        </ul>
        
        <h3><?php _e('5.2 With Your Consent', 'spiral-engine'); ?></h3>
        <ul>
            <li><?php _e('Healthcare providers you designate', 'spiral-engine'); ?></li>
            <li><?php _e('Emergency contacts in crisis situations', 'spiral-engine'); ?></li>
            <li><?php _e('Research partners (anonymized data only)', 'spiral-engine'); ?></li>
        </ul>
        
        <h3><?php _e('5.3 Legal Requirements', 'spiral-engine'); ?></h3>
        <p><?php _e('We may disclose your information if required by law, court order, or governmental authority.', 'spiral-engine'); ?></p>
    </section>
    
    <section id="data-security">
        <h2><?php _e('6. Data Security', 'spiral-engine'); ?></h2>
        <p><?php _e('We implement industry-standard security measures to protect your data:', 'spiral-engine'); ?></p>
        
        <ul>
            <li><?php _e('Encryption in transit (SSL/TLS) and at rest', 'spiral-engine'); ?></li>
            <li><?php _e('Regular security audits and vulnerability assessments', 'spiral-engine'); ?></li>
            <li><?php _e('Access controls and authentication requirements', 'spiral-engine'); ?></li>
            <li><?php _e('Employee training on data protection', 'spiral-engine'); ?></li>
            <li><?php _e('Incident response procedures', 'spiral-engine'); ?></li>
            <li><?php _e('Regular backups with encryption', 'spiral-engine'); ?></li>
        </ul>
        
        <p><?php _e('While we strive to protect your data, no method of transmission over the internet is 100% secure. We cannot guarantee absolute security.', 'spiral-engine'); ?></p>
    </section>
    
    <section id="data-retention">
        <h2><?php _e('7. Data Retention', 'spiral-engine'); ?></h2>
        <p><?php _e('We retain your data for as long as necessary to provide our services and comply with legal obligations:', 'spiral-engine'); ?></p>
        
        <table class="retention-table">
            <thead>
                <tr>
                    <th><?php _e('Data Type', 'spiral-engine'); ?></th>
                    <th><?php _e('Retention Period', 'spiral-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('Account information', 'spiral-engine'); ?></td>
                    <td><?php _e('Duration of account + 30 days', 'spiral-engine'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Assessment data', 'spiral-engine'); ?></td>
                    <td><?php _e('2 years from creation', 'spiral-engine'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Payment records', 'spiral-engine'); ?></td>
                    <td><?php _e('7 years (legal requirement)', 'spiral-engine'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Support communications', 'spiral-engine'); ?></td>
                    <td><?php _e('3 years from last contact', 'spiral-engine'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Analytics data', 'spiral-engine'); ?></td>
                    <td><?php _e('26 months', 'spiral-engine'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Deleted account data', 'spiral-engine'); ?></td>
                    <td><?php _e('30 days (recovery period)', 'spiral-engine'); ?></td>
                </tr>
            </tbody>
        </table>
    </section>
    
    <section id="your-rights">
        <h2><?php _e('8. Your Rights', 'spiral-engine'); ?></h2>
        <p><?php _e('You have the following rights regarding your personal data:', 'spiral-engine'); ?></p>
        
        <div class="rights-grid">
            <div class="right-item">
                <h3><?php _e('Right to Access', 'spiral-engine'); ?></h3>
                <p><?php _e('Request a copy of your personal data we hold', 'spiral-engine'); ?></p>
                <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo add_query_arg('action', 'access_request', home_url('/privacy-portal/')); ?>" class="button">
                    <?php _e('Request Access', 'spiral-engine'); ?>
                </a>
                <?php endif; ?>
            </div>
            
            <div class="right-item">
                <h3><?php _e('Right to Rectification', 'spiral-engine'); ?></h3>
                <p><?php _e('Correct inaccurate or incomplete personal data', 'spiral-engine'); ?></p>
                <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo get_edit_profile_url(); ?>" class="button">
                    <?php _e('Update Profile', 'spiral-engine'); ?>
                </a>
                <?php endif; ?>
            </div>
            
            <div class="right-item">
                <h3><?php _e('Right to Erasure', 'spiral-engine'); ?></h3>
                <p><?php _e('Request deletion of your personal data', 'spiral-engine'); ?></p>
                <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo add_query_arg('action', 'deletion_request', home_url('/privacy-portal/')); ?>" class="button">
                    <?php _e('Request Deletion', 'spiral-engine'); ?>
                </a>
                <?php endif; ?>
            </div>
            
            <div class="right-item">
                <h3><?php _e('Right to Restrict Processing', 'spiral-engine'); ?></h3>
                <p><?php _e('Limit how we use your personal data', 'spiral-engine'); ?></p>
            </div>
            
            <div class="right-item">
                <h3><?php _e('Right to Data Portability', 'spiral-engine'); ?></h3>
                <p><?php _e('Receive your data in a portable format', 'spiral-engine'); ?></p>
                <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo add_query_arg('action', 'export_request', home_url('/privacy-portal/')); ?>" class="button">
                    <?php _e('Export Data', 'spiral-engine'); ?>
                </a>
                <?php endif; ?>
            </div>
            
            <div class="right-item">
                <h3><?php _e('Right to Object', 'spiral-engine'); ?></h3>
                <p><?php _e('Object to certain types of processing', 'spiral-engine'); ?></p>
            </div>
            
            <div class="right-item">
                <h3><?php _e('Right to Withdraw Consent', 'spiral-engine'); ?></h3>
                <p><?php _e('Withdraw consent at any time', 'spiral-engine'); ?></p>
                <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo home_url('/privacy-portal/#consent'); ?>" class="button">
                    <?php _e('Manage Consent', 'spiral-engine'); ?>
                </a>
                <?php endif; ?>
            </div>
            
            <div class="right-item">
                <h3><?php _e('Right to Lodge a Complaint', 'spiral-engine'); ?></h3>
                <p><?php _e('Contact your supervisory authority', 'spiral-engine'); ?></p>
            </div>
        </div>
    </section>
    
    <section id="special-categories">
        <h2><?php _e('9. Special Categories of Data', 'spiral-engine'); ?></h2>
        
        <h3><?php _e('9.1 Health Data', 'spiral-engine'); ?></h3>
        <p>
            <?php _e('We process health-related data (mental wellness assessments) only with your explicit consent. You can withdraw this consent at any time, though this may limit our ability to provide certain services.', 'spiral-engine'); ?>
        </p>
        
        <h3><?php _e('9.2 Sensitive Personal Data', 'spiral-engine'); ?></h3>
        <p>
            <?php _e('We apply additional safeguards to sensitive data:', 'spiral-engine'); ?>
        </p>
        <ul>
            <li><?php _e('Enhanced encryption for health data', 'spiral-engine'); ?></li>
            <li><?php _e('Strict access controls', 'spiral-engine'); ?></li>
            <li><?php _e('Regular privacy impact assessments', 'spiral-engine'); ?></li>
            <li><?php _e('Pseudonymization where possible', 'spiral-engine'); ?></li>
        </ul>
        
        <h3><?php _e('9.3 Crisis Situations', 'spiral-engine'); ?></h3>
        <p>
            <?php _e('In situations where we detect potential crisis indicators (high assessment scores), we may:', 'spiral-engine'); ?>
        </p>
        <ul>
            <li><?php _e('Display crisis resources and helpline information', 'spiral-engine'); ?></li>
            <li><?php _e('Notify designated administrators (with your consent)', 'spiral-engine'); ?></li>
            <li><?php _e('Contact emergency services if we believe there is immediate danger (vital interests)', 'spiral-engine'); ?></li>
        </ul>
    </section>
    
    <section id="cookies">
        <h2><?php _e('10. Cookies', 'spiral-engine'); ?></h2>
        
        <h3><?php _e('10.1 What Are Cookies', 'spiral-engine'); ?></h3>
        <p>
            <?php _e('Cookies are small text files stored on your device that help us provide and improve our services.', 'spiral-engine'); ?>
        </p>
        
        <h3><?php _e('10.2 Types of Cookies We Use', 'spiral-engine'); ?></h3>
        <table class="cookies-table">
            <thead>
                <tr>
                    <th><?php _e('Cookie Type', 'spiral-engine'); ?></th>
                    <th><?php _e('Purpose', 'spiral-engine'); ?></th>
                    <th><?php _e('Duration', 'spiral-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('Essential', 'spiral-engine'); ?></td>
                    <td><?php _e('Authentication, security, site functionality', 'spiral-engine'); ?></td>
                    <td><?php _e('Session', 'spiral-engine'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Preferences', 'spiral-engine'); ?></td>
                    <td><?php _e('Language, timezone, display settings', 'spiral-engine'); ?></td>
                    <td><?php _e('1 year', 'spiral-engine'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Analytics', 'spiral-engine'); ?></td>
                    <td><?php _e('Usage patterns, feature effectiveness', 'spiral-engine'); ?></td>
                    <td><?php _e('2 years', 'spiral-engine'); ?></td>
                </tr>
            </tbody>
        </table>
        
        <h3><?php _e('10.3 Managing Cookies', 'spiral-engine'); ?></h3>
        <p>
            <?php _e('You can control cookies through your browser settings or our cookie preferences. Note that disabling essential cookies may affect site functionality.', 'spiral-engine'); ?>
        </p>
        <?php if (is_user_logged_in()) : ?>
        <a href="<?php echo home_url('/privacy-portal/#cookies'); ?>" class="button">
            <?php _e('Cookie Preferences', 'spiral-engine'); ?>
        </a>
        <?php endif; ?>
    </section>
    
    <section id="international">
        <h2><?php _e('11. International Transfers', 'spiral-engine'); ?></h2>
        <p>
            <?php _e('Your data may be transferred to and processed in countries other than your own. We ensure appropriate safeguards are in place:', 'spiral-engine'); ?>
        </p>
        <ul>
            <li><?php _e('Standard contractual clauses approved by regulatory authorities', 'spiral-engine'); ?></li>
            <li><?php _e('Adequacy decisions where applicable', 'spiral-engine'); ?></li>
            <li><?php _e('Privacy Shield certification (where valid)', 'spiral-engine'); ?></li>
            <li><?php _e('Binding corporate rules for intra-group transfers', 'spiral-engine'); ?></li>
        </ul>
    </section>
    
    <section id="children">
        <h2><?php _e('12. Children\'s Privacy', 'spiral-engine'); ?></h2>
        <p>
            <?php _e('Our services are not intended for children under the age of 13 (or 16 in certain jurisdictions). We do not knowingly collect personal data from children under these ages.', 'spiral-engine'); ?>
        </p>
        <p>
            <?php _e('If you are a parent or guardian and believe your child has provided us with personal data, please contact us immediately.', 'spiral-engine'); ?>
        </p>
    </section>
    
    <section id="changes">
        <h2><?php _e('13. Changes to This Policy', 'spiral-engine'); ?></h2>
        <p>
            <?php _e('We may update this Privacy Policy from time to time. We will notify you of any material changes by:', 'spiral-engine'); ?>
        </p>
        <ul>
            <li><?php _e('Posting the new policy on this page', 'spiral-engine'); ?></li>
            <li><?php _e('Updating the "Last Updated" date', 'spiral-engine'); ?></li>
            <li><?php _e('Sending you an email notification (for significant changes)', 'spiral-engine'); ?></li>
            <li><?php _e('Requiring your consent where legally required', 'spiral-engine'); ?></li>
        </ul>
        
        <p>
            <?php _e('Your continued use of our services after changes constitutes acceptance of the updated policy.', 'spiral-engine'); ?>
        </p>
    </section>
    
    <section id="contact">
        <h2><?php _e('14. Contact Us', 'spiral-engine'); ?></h2>
        <p><?php _e('If you have questions about this Privacy Policy or your personal data, please contact us:', 'spiral-engine'); ?></p>
        
        <div class="contact-info">
            <p><strong><?php _e('Data Protection Officer:', 'spiral-engine'); ?></strong></p>
            <p><?php echo esc_html($site_name); ?><br>
            <?php _e('Email:', 'spiral-engine'); ?> <a href="mailto:<?php echo esc_attr($admin_email); ?>?subject=Privacy%20Inquiry"><?php echo esc_html($admin_email); ?></a><br>
            <?php _e('Website:', 'spiral-engine'); ?> <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_url); ?></a></p>
            
            <p><strong><?php _e('Response Time:', 'spiral-engine'); ?></strong> <?php _e('We aim to respond to all privacy inquiries within 30 days.', 'spiral-engine'); ?></p>
        </div>
        
        <div class="supervisory-authority">
            <p><strong><?php _e('Supervisory Authority:', 'spiral-engine'); ?></strong></p>
            <p><?php _e('You have the right to lodge a complaint with your local data protection authority if you believe we have not handled your data appropriately.', 'spiral-engine'); ?></p>
        </div>
    </section>
    
    <?php if (is_user_logged_in()) : ?>
    <div class="privacy-actions">
        <h2><?php _e('Privacy Actions', 'spiral-engine'); ?></h2>
        <div class="action-buttons">
            <a href="<?php echo home_url('/privacy-portal/'); ?>" class="button button-primary">
                <?php _e('Privacy Portal', 'spiral-engine'); ?>
            </a>
            <a href="<?php echo add_query_arg('download', 'pdf', home_url('/privacy-policy/')); ?>" class="button">
                <?php _e('Download PDF', 'spiral-engine'); ?>
            </a>
            <a href="<?php echo home_url('/contact/?subject=privacy'); ?>" class="button">
                <?php _e('Contact DPO', 'spiral-engine'); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.spiralengine-privacy-policy {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}

.spiralengine-privacy-policy h1 {
    font-size: 2.5em;
    margin-bottom: 0.5em;
    color: #333;
}

.spiralengine-privacy-policy h2 {
    font-size: 1.8em;
    margin-top: 1.5em;
    margin-bottom: 0.8em;
    color: #444;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 0.3em;
}

.spiralengine-privacy-policy h3 {
    font-size: 1.3em;
    margin-top: 1.2em;
    margin-bottom: 0.6em;
    color: #555;
}

.policy-toc {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 5px;
    margin: 2em 0;
}

.policy-toc ol {
    margin-left: 20px;
}

.policy-toc a {
    text-decoration: none;
    color: #2196F3;
}

.policy-toc a:hover {
    text-decoration: underline;
}

table.legal-basis-table,
table.retention-table,
table.cookies-table {
    width: 100%;
    border-collapse: collapse;
    margin: 1em 0;
}

table.legal-basis-table th,
table.retention-table th,
table.cookies-table th {
    background: #f0f0f0;
    padding: 10px;
    text-align: left;
    font-weight: bold;
}

table.legal-basis-table td,
table.retention-table td,
table.cookies-table td {
    padding: 10px;
    border-bottom: 1px solid #e0e0e0;
}

.rights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 2em 0;
}

.right-item {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 5px;
    border: 1px solid #e0e0e0;
}

.right-item h3 {
    margin-top: 0;
    color: #2196F3;
}

.contact-info {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 5px;
    margin: 1em 0;
}

.privacy-actions {
    margin-top: 3em;
    padding: 20px;
    background: #e3f2fd;
    border-radius: 5px;
    text-align: center;
}

.action-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.button {
    display: inline-block;
    padding: 10px 20px;
    background: #2196F3;
    color: white;
    text-decoration: none;
    border-radius: 3px;
    transition: background 0.3s;
}

.button:hover {
    background: #1976D2;
}

.button-primary {
    background: #4CAF50;
}

.button-primary:hover {
    background: #388E3C;
}

@media (max-width: 768px) {
    .spiralengine-privacy-policy {
        padding: 10px;
    }
    
    .rights-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>

