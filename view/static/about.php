<div class="container my-5">
    <div class="row p-4 pb-0 pe-lg-0 pt-lg-5 align-items-center rounded-3 border shadow-lg">
        <div class="col-lg-7 p-3 p-lg-5 pt-lg-3">
            <h1 class="display-4 fw-bold lh-1 text-body-emphasis">about page</h1>
            <p class="lead">Quickly design and customize responsive mobile-first sites with Bootstrap, the world’s most popular front-end open source toolkit, featuring Sass variables and mixins, responsive grid system, extensive prebuilt components, and powerful JavaScript plugins.</p>
            <pre>

            <?php
            // print_r($_GET);
            $_ENV['DB_HOST']     = $_ENV['DB_HOST']     ?? 'localhost';
            $_ENV['DB_USER']     = $_ENV['DB_USER']     ?? 'root';
            $_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? '';
            $_ENV['DB_NAME']     = $_ENV['DB_NAME']     ?? 'hotel';

            require_once 'src/Database.php';
            require_once 'src/BaseModel.php';
            require_once 'src/UserModel.php';
            $db = new Database($_ENV['DB_NAME']);
            $users = new UserModel($db);
            $inactiveUsers = $users->getBy('vip', 'status');
            print_r($inactiveUsers);
            // echo("findByColumn('status') returns Bob", count($inactiveUsers) === 1);
            // echo("Returned user is Bob",               $inactiveUsers[0]['name'] === 'Bob');
            ?>
            </pre>
            <div class="d-grid gap-2 d-md-flex justify-content-md-start mb-4 mb-lg-3"> <button type="button" class="btn btn-primary btn-lg px-4 me-md-2 fw-bold" fdprocessedid="p741qs">Primary</button> <button type="button" class="btn btn-outline-secondary btn-lg px-4" fdprocessedid="gws7ct9">Default</button> </div>
        </div>
        <div class="col-lg-4 offset-lg-1 p-0 overflow-hidden shadow-lg"> <img class="rounded-lg-3" src="bootstrap-docs.png" alt="" width="720"> </div>
    </div>
</div>