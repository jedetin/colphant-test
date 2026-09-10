<?php
// include 'src/Generator.php';
include 'src/ConsoleLogger.php';
include 'src/SpecGenerator.php';
include 'src/ModelGenerator.php';
include 'src/DriverGenerator.php';
include 'src/UiGenerator.php';



$logger = new ConsoleLogger();

$generators = [
    // new SpecGenerator($logger), // working 
    new ModelGenerator($logger), // working 
    // new DriverGenerator($logger),
    // new UiGenerator($logger),
];

foreach ($generators as $generator) {
    try {
        $result = $generator->generate();

        if (!$result->success()) {
            $logger->error(
                $generator->name(),
                $result->message()
            );

            break;
        }

        $logger->success(
            $generator->name(),
            $result->message()
        );
    } catch (Throwable $e) {
        $logger->error(
            $generator->name(),
            $e->getMessage()
        );

        break;
    }
}
