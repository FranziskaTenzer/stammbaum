<?php
session_start();

// Consistent page structure
function render_header($title) {
    echo "<html>";
    echo "<head><title>" . htmlspecialchars($title) . "</title></head>";
    echo "<body><header><h1>" . htmlspecialchars($title) . "</h1></header>";
}

function render_footer() {
    echo "<footer>&copy; 2026 Franziska Tenzer</footer></body></html>";
}
?>