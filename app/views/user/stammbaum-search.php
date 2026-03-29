<?php
// ... previous code ... 

// Add parent names
if (!empty($data['vater'])) {
    echo '<td>Vater: ' . htmlspecialchars($data['vater']) . '</td>';
} else {
    echo '<td></td>';
}

if (!empty($data['mutter'])) {
    echo '<td>Mutter: ' . htmlspecialchars($data['mutter']) . '</td>';
} else {
    echo '<td></td>';
}

// Styling the Stammbaum link
echo '<a href="stammbaum.php" class="stammbaum-link">Stammbaum</a>';
// ... continue with the rest of the code ... 
?>

<style>
.stammbaum-link {
    color: purple;
}
</style>