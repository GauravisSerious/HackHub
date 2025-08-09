<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $title . ' - ' : ''; ?>Hackathon Management System</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom styles -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/modern-style.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Brand styling */
        .navbar-brand {
            font-weight: bold;
            font-size: 1.1rem;
            color: white !important;
            display: inline-flex;
            align-items: center;
            background: linear-gradient(to right, #00c6ff, #0072ff);
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 114, 255, 0.3);
            margin: 0 auto;
        }
        
        .code-icon {
            margin-right: 5px;
            font-weight: 900;
            font-size: 1.3rem;
            color: #fff;
            text-shadow: 0px 0px 6px rgba(255, 255, 255, 0.5);
        }
        
        .hub-text {
            background: linear-gradient(to right, #ffeb3b, #ffc107);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: 600;
        }
        
        header {
            background: #f8f9fa;
            padding: 0.4rem 0;
        }
    </style>
</head>
<body class="auth-page">
    <!-- Simple branding header -->
    <header class="py-2 shadow-sm d-flex justify-content-center">
        <div class="container">
            <div class="text-center">
                <a href="<?php echo BASE_URL; ?>" class="navbar-brand text-decoration-none">
                    <span class="code-icon">&lt;/&gt;</span> Hackhub
                </a>
            </div>
        </div>
    </header>
    <!-- Main content starts here --> 