        <!-- <p class="lead">Register</p> -->
        <?php
        include 'driver/Auth.php';
        ?>
        <div class="bg-light py-3 py-md-5">
            <div class="container">
                <div class="row justify-content-md-center">
                    <div class="col-12 col-md-11 col-lg-8 col-xl-7 col-xxl-6 mt-5">
                        <div class="bg-white p-4 p-md-5 rounded shadow-sm">
                            <div class="row">
                                <div class="col-12">
                                    <div class="text-center mb-5">
                                        <a href="">
                                            <img src="https://colpare.com/assets/images/logo-light.png" alt="BootstrapBrain Logo" width=""
                                                height="57">
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <form action="fp-generate" method="POST">
                                <h2 class="mb-4">Verify Account</h2>
                                <?php
                                include 'driver/error.php';
                                ?>
                                <div class="row gy-3 gy-md-4 overflow-hidden">
                                    <div class="col-12">
                                        <label for="email" class="form-label">Code <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-envelope"></i>
                                            </span>
                                            <input type="email" class="form-control" name="email" id="email" required>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-grid">
                                            <button name="generate_reset_token" class="btn btn-primary btn-lg" type="submit">Activate Account</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            <div class="row">
                                <div class="col-12">
                                    <hr class="mt-5 mb-4 border-secondary-subtle">
                                    <div class="d-flex gap-2 gap-md-4 flex-column flex-md-row justify-content-md-center">
                                        <a href="login" class="link-secondary text-decoration-none">Resend</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>