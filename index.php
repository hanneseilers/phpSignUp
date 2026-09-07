<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration Form</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="form-container">
        <h2>Event Registration Form</h2>
        <form action="process_form.php" method="POST">
            <div class="form-group">
                <label for="event_name">Event Name:</label>
                <select id="event_name" name="event_name" required>
                    <option value="">Select an event</option>
                    <option value="Birthday Party">Birthday Party</option>
                    <option value="School Play">School Play</option>
                    <option value="Summer Camp">Summer Camp</option>
                    <option value="Community Fair">Community Fair</option>
                    <option value="Holiday Celebration">Holiday Celebration</option>
                </select>
            </div>

            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required value="<?php 
                    $names = file('names.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if (!empty($names)) {
                        $random_name = $names[array_rand($names)];
                        $random_number = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                        echo $random_name . '_' . $random_number;
                    }
                ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="num_adults">Number of Adults:</label>
                    <input type="number" id="num_adults" name="num_adults" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="num_children">Number of Children:</label>
                    <input type="number" id="num_children" name="num_children" min="0" value="0">
                </div>
            </div>

            <div class="form-group">
                <label for="children_ages">Age of Children:</label>
                <textarea id="children_ages" name="children_ages" placeholder="Enter ages of children (e.g., 5, 8, 10)"></textarea>
            </div>

            <div class="form-group">
                <label for="comments">Comments:</label>
                <textarea id="comments" name="comments" placeholder="Any additional comments or special requests"></textarea>
            </div>

            <button type="submit" class="submit-btn">Submit Registration</button>
        </form>
    </div>
</body>
</html>
