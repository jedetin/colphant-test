<main class="container my-5">
    <p>test content</p>
    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);


    function section(string $title): void
    {
        global $isCli;
        $isCli
            ? print("\n\033[1;34m=== {$title} ===\033[0m\n")
            : print("<h3 style='color:#2563eb'>=== {$title} ===</h3>");
    }

    function pass(string $msg): void
    {
        global $isCli;
        $isCli
            ? print("  \033[0;32m[PASS]\033[0m {$msg}\n")
            : print("<p style='color:green'>✔ {$msg}</p>");
    }

    function fail(string $msg): void
    {
        global $isCli;
        $isCli
            ? print("  \033[0;31m[FAIL]\033[0m {$msg}\n")
            : print("<p style='color:red'>✘ {$msg}</p>");
    }

    function dump($label, $value): void
    {
        global $isCli;
        $out = print_r($value, true);
        $isCli
            ? print("  {$label}: {$out}\n")
            : print("<pre><b>{$label}</b>: " . htmlspecialchars($out) . "</pre>");
    }

    function assert_test(string $label, bool $condition): void
    {
        $condition ? pass($label) : fail($label);
    }

    $_ENV['DB_HOST']     = $_ENV['DB_HOST']     ?? 'localhost';
    $_ENV['DB_USER']     = $_ENV['DB_USER']     ?? 'root';
    $_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? '';
    $_ENV['DB_NAME']     = $_ENV['DB_NAME']     ?? 'hotel';

    require_once 'src/Database.php';
    require_once 'src/BaseModel.php';
    require_once 'src/UserModel.php';

    try {
        $db = new Database($_ENV['DB_NAME']);
        pass("Connected to '{$_ENV['DB_NAME']}'");
    } catch (RuntimeException $e) {
        fail("Connection error: " . $e->getMessage());
        exit(1);
    }

    section("Step 2: UserModel — CREATE");

    $users = new UserModel($db);

    $userId1 = $users->add([
        'name'   => 'Alice',
        'email'  => 'alice@example.com',
        'status' => 'active',
        // 'score'  => 98.5,          // float — tests 'd' type binding
    ]);

    $userId2 = $users->add([
        'name'   => 'Bob',
        'email'  => 'bob@example.com',
        'status' => 'inactive',
        // 'score'  => null,          // null — tests null handling
    ]);

    assert_test("User 1 inserted (Alice), ID: {$userId1}", $userId1 > 0);
    assert_test("User 2 inserted (Bob), ID: {$userId2}",   $userId2 > 0);


    section("Step 3: UserModel — READ by ID");

    $alice = $users->getById($userId1);
    dump("Alice", $alice);

    assert_test("Alice fetched by ID",              !empty($alice));
    assert_test("Alice name matches",               $alice['name']   === 'Alice');
    assert_test("Alice email matches",              $alice['email']  === 'alice@example.com');
    // assert_test("Alice score is float",             (float)$alice['score'] === 98.5);

    $bob = $users->getById($userId2);
    dump("Bob", $bob);

    assert_test("Bob fetched by ID",                !empty($bob));
    // assert_test("Bob score is null",                is_null($bob['score']));
    section("Step 4: UserModel — READ all");

    $all = $users->getAll();
    assert_test("findAll() returns 2 users", count($all) === 2);



    section("Step 5: UserModel — READ by column");

    $inactiveUsers = $users->getBy('inactive', 'status');
    assert_test("findByColumn('status') returns Bob", count($inactiveUsers) === 1);
    assert_test("Returned user is Bob",               $inactiveUsers[0]['name'] === 'Bob');


    // section("Step 6: Security — blocked column injection");

    // try {
    //     $users->getBy('anything', 'password'); // 'password' not in allowedColumns
    //     fail("Should have thrown — column not whitelisted");
    // } catch (InvalidArgumentException $e) {
    //     pass("Blocked disallowed column: " . $e->getMessage());
    // }

    section("Step 7: UserModel — UPDATE");

    $updated = $users->edit($userId1, [
        'name'   => 'Alice Updated',
        'status' => 'vip',
        // 'score'  => 99.9,
    ]);

    assert_test("Update returned true", $updated === true);

    $aliceUpdated = $users->getById($userId1);
    assert_test("Name updated correctly",   $aliceUpdated['name']   === 'Alice Updated');
    assert_test("Status updated correctly", $aliceUpdated['status'] === 'vip');
    // assert_test("Score updated correctly",  (float)$aliceUpdated['score'] === 99.9);




    section("Step 9: UserModel — DELETE");

    $deleted = $users->remove($userId2);
    assert_test("Bob deleted (returned true)", $deleted === true);

    $shouldBeNull = $users->getById($userId2);
    assert_test("Bob no longer retrievable",   empty($shouldBeNull));

    $remaining = $users->getAll();
    assert_test("Only 1 user remains",         count($remaining) === 1);



    section("Step 10: Error handling — invalid query");

    try {
        // Temporarily bypass model to test Database::prepare() directly
        $db->prepare("SELECT * FROM non_existent_table_xyz WHERE id = ?");
        fail("Should have thrown on bad prepare");
    } catch (RuntimeException $e) {
        pass("RuntimeException thrown correctly: " . $e->getMessage());
    }


    section("Step 11: Database — setDatabase() switch");

    try {
        $db->setDatabase($_ENV['DB_NAME']); // switch back to same DB (no-op but tests close+reconnect)
        $result = $db->query("SELECT 1 AS ping");
        $row    = $result->fetch_assoc();
        assert_test("setDatabase() reconnects cleanly", $row['ping'] == 1);
    } catch (RuntimeException $e) {
        fail("setDatabase() failed: " . $e->getMessage());
    }

    ?>
</main>