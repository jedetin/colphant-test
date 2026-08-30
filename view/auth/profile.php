<?php

if (empty($_SESSION['user_id'])) {
    header('Location:/login');
    exit;
}

?>
<div class="float-end"><a href="logout" class="btn btn-danger">Logout</a></div>
<div class="row w-100 m-0 justify-content-center mt-5">

    <div class="col-12 col-xl-10 mb-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <h4 class="mb-1 fw-bold text-dark"><i class="fas fa-sliders-h text-primary me-2"></i>Account Management</h4>
                        <p class="text-muted small mb-0">Configure profile specifics, verify login history, and update security infrastructure details.</p>
                    </div>
                    <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
                        <span class="badge bg-light text-dark border p-2"><i class="fas fa-clock text-warning me-1"></i> Plan: Enterprise Elite</span>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="row g-0 border-top">

                    <div class="col-md-3 border-end bg-light p-3">
                        <div class="nav flex-column nav-pills" id="settingsTabs" role="tablist" aria-orientation="vertical">
                            <button class="nav-link active text-start py-2.5 mb-1" id="profile-tab" data-bs-toggle="pill" data-bs-target="#profile-panel" type="button" role="tab"><i class="fas fa-user-cog me-2"></i>Profile Details</button>
                            <button class="nav-link text-start py-2.5 mb-1" id="security-tab" data-bs-toggle="pill" data-bs-target="#security-panel" type="button" role="tab"><i class="fas fa-user-shield me-2"></i>Security Logs</button>
                            <button class="nav-link text-start py-2.5" id="billing-tab" data-bs-toggle="pill" data-bs-target="#billing-panel" type="button" role="tab"><i class="fas fa-credit-card me-2"></i>Billing Profile</button>
                        </div>
                    </div>

                    <div class="col-md-9 p-4">
                        <div class="tab-content" id="settingsTabsContent">

                            <div class="tab-pane fade show active" id="profile-panel" role="tabpanel" aria-labelledby="profile-tab">
                                <h5 class="fw-bold mb-3 text-secondary">General Identity Information</h5>
                                <div class="table-responsive mb-4">
                                    <table class="table table-borderless align-middle border rounded bg-white">
                                        <tbody>
                                            <tr class="border-bottom">
                                                <td class="text-muted ps-3 py-3 small" style="width: 30%;">Account Owner Name</td>
                                                <td class="fw-medium text-dark py-3">
                                                    <?php print_r($_SESSION); ?>
                                                </td>
                                                <td class="text-end pe-3 py-3"><button class="btn btn-sm btn-light border"><i class="fas fa-pen"></i></button></td>
                                            </tr>
                                            <tr class="border-bottom">
                                                <td class="text-muted ps-3 py-3 small">Primary Email Status</td>
                                                <td class="fw-medium text-dark py-3">
                                                    <span class="me-2">alex.jones@company.com</span>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check me-1"></i>Verified</span>
                                                </td>
                                                <td class="text-end pe-3 py-3"><button class="btn btn-sm btn-light border">Update</button></td>
                                            </tr>
                                            <tr class="border-bottom">
                                                <td class="text-muted ps-3 py-3 small">Connected Organization</td>
                                                <td class="fw-medium text-dark py-3">CyberDyne Industries Inc.</td>
                                                <td class="text-end pe-3 py-3"><span class="text-muted small">ID: #99210</span></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted ps-3 py-3 small">Localized Timezone</td>
                                                <td class="text-dark py-3">GMT -05:00 (Eastern Standard Time)</td>
                                                <td class="text-end pe-3 py-3"><button class="btn btn-sm btn-light border"><i class="fas fa-globe"></i></button></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end">
                                    <button class="btn btn-primary px-4">Save Profile Configuration</button>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="security-panel" role="tabpanel" aria-labelledby="security-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold text-secondary mb-0">Session & Sign-In History</h5>
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-ban me-1"></i>Revoke All Sessions</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table border table-hover bg-white align-middle small">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Timestamp / Date</th>
                                                <th>Device / Client</th>
                                                <th>IP Address</th>
                                                <th>Location Context</th>
                                                <th class="pe-3 text-end">Status Outcome</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ps-3 fw-medium text-dark">Jul 02, 2026 00:04</td>
                                                <td><i class="fab fa-chrome text-primary me-2"></i>Chrome v124 (MacOS)</td>
                                                <td><code>192.168.1.45</code></td>
                                                <td>Bengaluru, India (Current)</td>
                                                <td class="pe-3 text-end"><span class="badge bg-success">Active Session</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ps-3 text-muted">Jun 28, 2026 14:22</td>
                                                <td><i class="fab fa-safari text-info me-2"></i>Safari Mobile (iPhone)</td>
                                                <td><code>103.24.11.90</code></td>
                                                <td>Mumbai, India</td>
                                                <td class="pe-3 text-end"><span class="badge bg-secondary">Expired Access</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ps-3 text-muted">Jun 15, 2026 09:11</td>
                                                <td><i class="fab fa-windows text-secondary me-2"></i>Edge Client (Windows PC)</td>
                                                <td><code>45.112.32.18</code></td>
                                                <td>London, UK</td>
                                                <td class="pe-3 text-end"><span class="badge bg-danger">Blocked / Flagged</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="billing-panel" role="tabpanel" aria-labelledby="billing-tab">
                                <h5 class="fw-bold mb-3 text-secondary">Payment Instruments</h5>
                                <div class="card border bg-light shadow-none mb-3">
                                    <div class="card-body d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white p-2 border rounded me-3">
                                                <i class="fab fa-cc-visa fa-2x text-primary"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 fw-bold">Visa Professional Ending in •••• 4242</p>
                                                <p class="text-muted small mb-0">Expiration Date Target: 12/2029</p>
                                            </div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-secondary">Update Card</button>
                                    </div>
                                </div>
                                <p class="text-muted small"><i class="fas fa-info-circle me-1"></i> Next renewal automated transaction processing scheduled for August 01, 2026.</p>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>