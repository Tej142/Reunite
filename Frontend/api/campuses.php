<?php
/**
 * Public Campuses API
 * Returns list of active campuses for registration and user profiles
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../Backend/config/config.php';

$campuses = [];

if (isset($conn) && $conn) {
    $res = @$conn->query("SELECT id, name, code, address, status FROM campuses WHERE status = 'active' ORDER BY name ASC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $campuses[] = [
                'id'      => (int)$r['id'],
                'name'    => $r['name'],
                'code'    => $r['code'],
                'address' => $r['address'] ?? ''
            ];
        }
    }
}

// Fallback in case database is briefly unreachable
if (empty($campuses)) {
    $campuses = [
        ['id' => 1, 'name' => 'Sri Venkateswara Govt Polytechnic', 'code' => 'SVGP', 'address' => 'KT Road, Tirupati, AP'],
        ['id' => 3, 'name' => 'Government polytechinc pillaripattu, Puttur.', 'code' => 'PLPT', 'address' => 'Pillaripattu, Puttur, AP'],
        ['id' => 4, 'name' => 'Venkata Perumal', 'code' => 'VKPT', 'address' => 'Tirupati, AP'],
        ['id' => 2, 'name' => 'Tirupati Central Campus', 'code' => 'TCC', 'address' => 'Tirupati Central, AP'],
        ['id' => 5, 'name' => 'University of California, Berkeley', 'code' => 'UCB', 'address' => 'Berkeley, CA, USA'],
        ['id' => 6, 'name' => 'Columbia University', 'code' => 'COLUMBIA', 'address' => 'New York, NY, USA'],
        ['id' => 7, 'name' => 'Georgia Institute of Technology (Georgia Tech)', 'code' => 'GATECH', 'address' => 'Atlanta, GA, USA'],
        ['id' => 8, 'name' => 'Carnegie Mellon University (CMU)', 'code' => 'CMU', 'address' => 'Pittsburgh, PA, USA'],
        ['id' => 9, 'name' => 'Other Academic Institution', 'code' => 'OTHER', 'address' => 'Global / Other']
    ];
}

echo json_encode([
    'success'  => true,
    'count'    => count($campuses),
    'campuses' => $campuses
]);
