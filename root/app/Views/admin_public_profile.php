<?php $this->extend('_layout_admin'); ?>
<?= $this->section('content') ?>
<div class="page-profile">
    <div class="pf-hero">
        <div class="pf-hero-main">
          <span class="pf-avatar">
            <img src="<?= $user['avatar_path'] ?>" alt="<?= $user['display_name'] ?>">
          </span>
            <div class="pf-identity">
                <h1 class="pf-name"><?= $user['display_name'] ?></h1>
                <div class="pf-role">Web Designer · <span>Lueilwitz, Wisoky and Leuschke</span></div>
                <div class="pf-meta">
                    <span><i class="ph ph-map-pin"></i> New York, USA</span>
                    <span><i class="ph ph-envelope"></i> k.anderson@example.com</span>
                    <span><i class="ph ph-calendar-blank"></i> Member since 2023</span>
                </div>
            </div>
            <div class="pf-hero-actions">
                <button class="pf-btn-light"><i class="ph ph-share-network"></i> Share</button>
                <button class="pf-btn-gradient" data-pf-tab="edit"><i class="ph ph-pencil-simple"></i> Edit Profile
                </button>
            </div>
        </div>
        <div class="pf-hero-stats">
            <div class="pf-hero-stat">
                <span class="pf-hero-stat-value">36</span>
                <span class="pf-hero-stat-label">Projects</span>
            </div>
            <div class="pf-hero-stat">
                <span class="pf-hero-stat-value">1.2k</span>
                <span class="pf-hero-stat-label">Followers</span>
            </div>
            <div class="pf-hero-stat">
                <span class="pf-hero-stat-value">184</span>
                <span class="pf-hero-stat-label">Following</span>
            </div>
            <div class="pf-hero-stat">
                <span class="pf-hero-stat-value">4.9</span>
                <span class="pf-hero-stat-label">Rating</span>
            </div>
        </div>
    </div>
    <div class="pf-layout">
        <!-- Left Column -->
        <div class="pf-side">
            <!-- Skills -->
            <div class="card">
                <div class="dash-card-header">
                    <div>
                        <h5 class="dash-card-title">Skills</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="pf-tags">
                        <span class="pf-tag">UI Design</span>
                        <span class="pf-tag">Figma</span>
                        <span class="pf-tag">HTML/CSS</span>
                        <span class="pf-tag">JavaScript</span>
                        <span class="pf-tag">Branding</span>
                        <span class="pf-tag">Prototyping</span>
                        <span class="pf-tag">Design Systems</span>
                    </div>
                </div>
            </div>

            <!-- Social Links -->
            <div class="card">
                <div class="dash-card-header">
                    <div>
                        <h5 class="dash-card-title">On the Web</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="pf-links">
                        <a href="#" class="pf-link">
                            <span class="pf-link-icon"><i class="ph ph-globe"></i></span>
                            <span class="pf-link-text">kevinanderson.design</span>
                            <i class="ph ph-arrow-up-right pf-link-arrow"></i>
                        </a>
                        <a href="#" class="pf-link">
                            <span class="pf-link-icon"><i class="ph ph-dribbble-logo"></i></span>
                            <span class="pf-link-text">dribbble.com/kevinand</span>
                            <i class="ph ph-arrow-up-right pf-link-arrow"></i>
                        </a>
                        <a href="#" class="pf-link">
                            <span class="pf-link-icon"><i class="ph ph-github-logo"></i></span>
                            <span class="pf-link-text">github.com/kevinand</span>
                            <i class="ph ph-arrow-up-right pf-link-arrow"></i>
                        </a>
                        <a href="#" class="pf-link">
                            <span class="pf-link-icon"><i class="ph ph-linkedin-logo"></i></span>
                            <span class="pf-link-text">in/kevin-anderson</span>
                            <i class="ph ph-arrow-up-right pf-link-arrow"></i>
                        </a>
                        <a href="#" class="pf-link">
                            <span class="pf-link-icon"><i class="ph ph-x-logo"></i></span>
                            <span class="pf-link-text">@kevinanderson</span>
                            <i class="ph ph-arrow-up-right pf-link-arrow"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Tabbed Card -->
        <div class="card pf-main-card">
            <div class="pf-tabs-bar">
                <ul class="nav pf-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pf-overview" type="button"
                                role="tab" aria-selected="true">Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pf-edit-tab" data-bs-toggle="tab" data-bs-target="#pf-edit"
                                type="button" role="tab" aria-selected="false" tabindex="-1">Edit Profile
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pf-password" type="button"
                                role="tab" aria-selected="false" tabindex="-1">Password
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content">
                    <!-- Overview -->
                    <div class="tab-pane fade show active" id="pf-overview" role="tabpanel">
                        <h6 class="pf-section-title">About</h6>
                        <p class="pf-about">
                            Passionate web designer focused on crafting clean, user-friendly interfaces. I love turning
                            complex
                            problems into simple, beautiful solutions — and I've spent the last decade doing exactly
                            that for
                            startups and established brands alike.
                        </p>

                        <h6 class="pf-section-title">Profile Details</h6>
                        <div class="pf-detail-list">
                            <div class="pf-detail">
                                <span class="pf-detail-label"><i class="ph ph-user"></i> Full Name</span>
                                <span class="pf-detail-value">Kevin Anderson</span>
                            </div>
                            <div class="pf-detail">
                                <span class="pf-detail-label"><i class="ph ph-buildings"></i> Company</span>
                                <span class="pf-detail-value">Lueilwitz, Wisoky and Leuschke</span>
                            </div>
                            <div class="pf-detail">
                                <span class="pf-detail-label"><i class="ph ph-briefcase"></i> Job</span>
                                <span class="pf-detail-value">Web Designer</span>
                            </div>
                            <div class="pf-detail">
                                <span class="pf-detail-label"><i class="ph ph-map-pin"></i> Country</span>
                                <span class="pf-detail-value">USA</span>
                            </div>
                            <div class="pf-detail">
                                <span class="pf-detail-label"><i class="ph ph-house"></i> Address</span>
                                <span class="pf-detail-value">A108 Adam Street, New York, NY 535022</span>
                            </div>
                            <div class="pf-detail">
                                <span class="pf-detail-label"><i class="ph ph-phone"></i> Phone</span>
                                <span class="pf-detail-value">(436) 486-3538 x29071</span>
                            </div>
                            <div class="pf-detail">
                                <span class="pf-detail-label"><i class="ph ph-envelope"></i> Email</span>
                                <span class="pf-detail-value">k.anderson@example.com</span>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Profile -->
                    <div class="tab-pane fade" id="pf-edit" role="tabpanel" aria-labelledby="pf-edit-tab">
                        <form>
                            <div class="pf-edit-avatar">
                                <img src="assets/img/profile-img.webp" alt="Profile">
                                <div class="pf-edit-avatar-actions">
                                    <span class="pf-edit-avatar-name">Profile photo</span>
                                    <span class="pf-edit-avatar-hint">PNG or WEBP, at least 400×400px.</span>
                                    <div class="pf-edit-avatar-btns">
                                        <button type="button" class="users-btn-ghost"><i
                                                class="ph ph-upload-simple"></i> Upload
                                        </button>
                                        <button type="button" class="pf-btn-remove"><i class="ph ph-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="Kevin Anderson">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Job Title</label>
                                    <input type="text" class="form-control" value="Web Designer">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">About</label>
                                    <textarea class="form-control" rows="3">Passionate web designer focused on crafting clean, user-friendly interfaces.</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Company</label>
                                    <input type="text" class="form-control" value="Lueilwitz, Wisoky and Leuschke">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Country</label>
                                    <input type="text" class="form-control" value="USA">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control"
                                           value="A108 Adam Street, New York, NY 535022">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" value="(436) 486-3538 x29071">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="k.anderson@example.com">
                                </div>
                            </div>

                            <div class="pf-form-footer">
                                <button type="submit" class="users-btn-primary"><i class="ph ph-check"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="tab-pane fade" id="pf-password" role="tabpanel">
                        <form>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" placeholder="Enter current password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" placeholder="Enter new password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Re-enter New Password</label>
                                    <input type="password" class="form-control" placeholder="Repeat new password">
                                </div>
                            </div>
                            <div class="ue-hint mt-2"><i class="ph ph-info"></i> Minimum 8 characters with at least one
                                number and one symbol.
                            </div>
                            <div class="pf-form-footer">
                                <button type="submit" class="users-btn-primary"><i class="ph ph-key"></i> Change
                                    Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
