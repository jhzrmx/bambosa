<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, skip the landing page and go straight to dashboard
if (is_logged_in()) {
    redirect_to_dashboard();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>BAMBOSA Irrigation Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<header class="navbar landing-navbar">
  <div class="brand">
    <span class="brand-mark" aria-hidden="true">🌾</span>
    <span>
      BAMBOSA
      <span class="sub">Irrigation Management Platform</span>
    </span>
  </div>
  <nav>
    <a href="<?= base_url('login.php') ?>" class="btn-outline">Log In</a>
    <a href="<?= base_url('signup.php') ?>" class="btn-solid">Sign Up</a>
  </nav>
</header>

<main class="landing-page">

  <section class="landing-hero">
    <div class="landing-hero-content">
      <div class="eyebrow">BAMBOSA IRRIGATORS ASSOCIATION</div>

      <h1>Smarter irrigation management for the BAMBOSA community.</h1>

      <p class="hero-lead">
        Keep member records, water rotation schedules, irrigation service fees,
        machinery reservations, and farm information organized in one place.
      </p>

      <div class="hero-actions">
        <a href="<?= base_url('signup.php') ?>" class="primary">Get Started <span>→</span></a>
        <a href="<?= base_url('login.php') ?>" class="secondary">I already have an account</a>
      </div>

      <p class="hero-note">
        For registered BAMBOSA members and association officers.
      </p>
    </div>

    <div class="irrigation-illustration" aria-hidden="true">
      <div class="illustration-sun"></div>
      <div class="illustration-cloud cloud-one"></div>
      <div class="illustration-cloud cloud-two"></div>
      <div class="illustration-hill hill-back"></div>
      <div class="illustration-hill hill-front"></div>

      <div class="water-channel">
        <span></span><span></span><span></span>
      </div>

      <div class="rice-field field-one"></div>
      <div class="rice-field field-two"></div>
      <div class="rice-field field-three"></div>

      <div class="farmer" title="Farm">
        <div class="farmer-head"></div>
        <div class="farmer-body"></div>
      </div>
    </div>
  </section>

  <section class="landing-section features-section">
    <div class="section-heading">
      <div class="eyebrow">ONE PLATFORM</div>
      <h2>Everything BAMBOSA needs to stay organized.</h2>
      <p>
        Designed around the day-to-day administrative needs of the irrigators
        association and its members.
      </p>
    </div>

    <div class="feature-grid">
      <article class="feature-card landing-feature-card">
        <div class="feature-icon">💧</div>
        <h3>Rotation Scheduling</h3>
        <p>
          Organize water release schedules for sectors and help reduce conflicts
          between farmers.
        </p>
      </article>

      <article class="feature-card landing-feature-card">
        <div class="feature-icon">₱</div>
        <h3>ISF Records</h3>
        <p>
          Keep irrigation service fee computations and payment records easier
          to manage and review.
        </p>
      </article>

      <article class="feature-card landing-feature-card">
        <div class="feature-icon">🚜</div>
        <h3>Machinery Reservations</h3>
        <p>
          Record shared farming equipment reservations and help prevent
          conflicting schedules.
        </p>
      </article>

      <article class="feature-card landing-feature-card">
        <div class="feature-icon">🗺️</div>
        <h3>Farm Mapping</h3>
        <p>
          Keep member and farm-location information available for planning and
          association records.
        </p>
      </article>
    </div>
  </section>

  <section class="landing-section community-section">
    <div class="community-copy">
      <div class="eyebrow">BUILT FOR THE COMMUNITY</div>
      <h2>Useful for both farmers and association officers.</h2>
      <p>
        The platform brings the records and services that matter to BAMBOSA
        into a single, organized system.
      </p>
    </div>

    <div class="role-grid">
      <article class="role-card">
        <div class="role-icon">👨‍🌾</div>
        <div>
          <h3>For Members &amp; Farmers</h3>
          <ul>
            <li>View your member and farm information</li>
            <li>Check your irrigation schedule</li>
            <li>Review ISF and payment records</li>
            <li>Request shared machinery reservations</li>
          </ul>
        </div>
      </article>

      <article class="role-card">
        <div class="role-icon">📋</div>
        <div>
          <h3>For Association Officers</h3>
          <ul>
            <li>Manage member records</li>
            <li>Organize irrigation schedules</li>
            <li>Manage ISF and payment records</li>
            <li>Manage machinery and farm information</li>
          </ul>
        </div>
      </article>
    </div>
  </section>

  <section class="landing-section workflow-section">
    <div class="section-heading">
      <div class="eyebrow">HOW IT WORKS</div>
      <h2>Keep the irrigation workflow simple.</h2>
    </div>

    <div class="workflow">
      <div class="workflow-step">
        <span class="step-number">01</span>
        <div>
          <h3>Manage members</h3>
          <p>Maintain the association's member and farm records.</p>
        </div>
      </div>

      <div class="workflow-line"></div>

      <div class="workflow-step">
        <span class="step-number">02</span>
        <div>
          <h3>Organize irrigation</h3>
          <p>Publish water rotation schedules for the community.</p>
        </div>
      </div>

      <div class="workflow-line"></div>

      <div class="workflow-step">
        <span class="step-number">03</span>
        <div>
          <h3>Track services</h3>
          <p>Keep ISF, payment, and machinery records organized.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="landing-cta">
    <div>
      <div class="eyebrow">BAMBOSA IRRIGATION MANAGEMENT</div>
      <h2>Ready to manage your records in one place?</h2>
      <p>Sign in if you already have an account, or create an account to get started.</p>
    </div>
    <div class="cta-actions">
      <a href="<?= base_url('signup.php') ?>" class="primary">Get Started <span>→</span></a>
      <a href="<?= base_url('login.php') ?>" class="secondary">Log In</a>
    </div>
  </section>

</main>

<footer class="site-footer landing-footer">
  <div>
    <strong>BAMBOSA Irrigators Association</strong>
    <span>Barangay Bikal, Libmanan, Camarines Sur</span>
  </div>
  <span>&copy; <?= date('Y') ?> BAMBOSA Irrigation Management</span>
</footer>

</body>
</html>
