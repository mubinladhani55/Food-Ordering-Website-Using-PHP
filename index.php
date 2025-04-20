<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Foodie Express</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="CSS/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    /* Full-screen Hero Section with Background Slider */
    .hero-section {
      position: relative;
      height: 100vh;
      min-height: 600px;
      overflow: hidden;
      background-color: #000;
      color: white;
    }
    
    .hero-content {
      position: relative;
      z-index: 3;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      padding: 20px;
    }
    
    .bg-slide {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-size: cover;
      background-position: center;
      opacity: 0;
      transition: opacity 1.5s ease-in-out;
      z-index: 1;
    }
    
    .bg-slide.active {
      opacity: 1;
    }
    
    .hero-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.4);
      z-index: 2;
    }
    
    /* Restaurant Grid Adjustments */
    .restaurant-grid {
      padding: 50px 0;
      background-color: #f8f9fa;
    }
    
    /* Navbar Fix */
    .navbar {
      position: relative;
      z-index: 1000;
    }
    
    /* Updated Search Container Width */
    .search-container {
      max-width: 660px;
      margin: 0 auto;
    }
    
    #searchResults {
      max-width: 500px;
      width: 100%;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="index.php">
        <i class="fas fa-utensils"></i> Foodie Express
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link active" href="index.php">Home</a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
              Restaurants
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="chinese_menu.php?restaurant=WokOnFire">Wok on Fire</a></li>
              <li><a class="dropdown-item" href="pizza_menu.php?restaurant=PizzaHub">Pizza Hub</a></li>
              <li><a class="dropdown-item" href="tandoor_menu.php?restaurant=TandoorHut">Tandoor Hut</a></li>
            </ul>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Offers</a>
          </li>
          <?php
          if (isset($_SESSION['user_id'])) {
            echo '<li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                      <i class="fas fa-user-circle"></i> ' . htmlspecialchars($_SESSION['fullname']) . '
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li><a class="dropdown-item" href="myprofile.php"><i class="fas fa-id-card me-2"></i>My Profile</a></li>
                      <li><a class="dropdown-item" href="myorders.php"><i class="fas fa-list me-2"></i>My Orders</a></li>
                      <li><hr class="dropdown-divider"></li>
                      <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                  </li>';
          } 
          elseif (isset($_SESSION['restaurant_logged_in']) && $_SESSION['restaurant_logged_in'] === true) {
            echo '<li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="restaurantDropdown" role="button" data-bs-toggle="dropdown">
                      <i class="fas fa-store"></i> ' . htmlspecialchars($_SESSION['restaurant_name']) . '
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li><a class="dropdown-item" href="restaurant_logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                  </li>';
          }
          else {
            echo '<li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="loginDropdown" role="button" data-bs-toggle="dropdown">
                      <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li><a class="dropdown-item" href="login.php"><i class="fas fa-user me-2"></i>User Login</a></li>
                      <li><a class="dropdown-item" href="restaurant_login.php"><i class="fas fa-store me-2"></i>Restaurant Login</a></li>
                    </ul>
                  </li>';
          }
          ?>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero Section with Full Background Slider -->
  <section class="hero-section">
    <!-- Background Slides -->
    <div class="bg-slide active" style="background-image: url('Images/Hotel.jpg');"></div>
    <div class="bg-slide" style="background-image: url('Images/Hotel2.jpg');"></div>
    <div class="bg-slide" style="background-image: url('Images/Hotel3.jpg');"></div>
    
    <!-- Overlay -->
    <div class="hero-overlay"></div>
    
    <!-- Content -->
    <div class="container hero-content">
      <h1 class="display-4 mb-4">Hotel Grand Hayat</h1>
      <p class="lead mb-5">Order from your favorite restaurants and get it delivered to your doorstep.</p>
      <div class="input-group mb-3 search-container">
        <input type="text" class="form-control" id="restaurantSearch" placeholder="Enter your restaurant...">
        <button class="btn btn-primary" type="button" id="searchButton">
          <i class="fas fa-search"></i> Find Restaurant
        </button>
      </div>
      <div id="searchResults" class="mt-2 mx-auto">
        <!-- Search results will appear here -->
      </div>
    </div>
  </section>

  <!-- Restaurant Grid -->
  <section class="restaurant-grid">
    <div class="container">
      <h2 class="text-center mb-4">Popular Restaurants</h2>
      <div class="row">
        <div class="col-md-4 mb-4">
          <div class="card">
            <div class="newtest">
              <img src="Images/Noodles.jpg" class="card-img-top" alt="Restaurant 1">
            </div>
            <div class="card-body">
              <h5 class="card-title"><u>Wok on Fire</u></h5>
              <p class="card-text">A Fusion of Fire and Flavor!</p>
              <div class="d-flex justify-content-between align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                  <a href="chinese_menu.php?restaurant=WokOnFire" class="btn btn-primary text-decoration-underline">View Menu</a>
                <?php else: ?>
                  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">View Menu</button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4 mb-4">
          <div class="card">
            <div class="newtest">
              <img src="Images/Pizza.jpg" class="card-img-top" alt="Restaurant 2">
            </div>
            <div class="card-body">
              <h5 class="card-title"><u>Pizza Hub</u></h5>
              <p class="card-text">Where Pizzas and Dreams Come Alive.</p>
              <div class="d-flex justify-content-between align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                  <a href="pizza_menu.php?restaurant=PizzaHub" class="btn btn-primary text-decoration-underline">View Menu</a>
                <?php else: ?>
                  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">View Menu</button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4 mb-4">
          <div class="card">
            <div class="newtest">
              <img src="Images/Tandoori Chicken.jpg" class="card-img-top" alt="Restaurant 3">
            </div>
            <div class="card-body">
              <h5 class="card-title"><u>Tandoor Hut</u></h5>
              <p class="card-text">Where Kebabs Meet Tradition.</p>
              <div class="d-flex justify-content-between align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                  <a href="tandoor_menu.php?restaurant=TandoorHut" class="btn btn-primary text-decoration-underline">View Menu</a>
                <?php else: ?>
                  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">View Menu</button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Login Required Modal -->
  <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="loginModalLabel">Login Required</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>You need to be logged in as a user to view restaurant menus. Please login or register first.</p>
          <div class="text-center">
            <i class="fas fa-user-lock fa-4x text-primary mb-3"></i>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt me-2"></i>Go to Login</a>
          <a href="register.php" class="btn btn-success"><i class="fas fa-user-plus me-2"></i>Register</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-dark text-white text-center py-3">
    <p>&copy; 2023 Foodie Express. All rights reserved.</p>
    <div class="social-icons">
      <a href="#" class="text-white me-3"><i class="fab fa-facebook"></i></a>
      <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
      <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
    </div>
  </footer>

  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="JS/search.js"></script>
  <script src="JS/script.js"></script>
  
  <script>
    // Background Image Slider
    document.addEventListener('DOMContentLoaded', function() {
      const slides = document.querySelectorAll('.bg-slide');
      let currentSlide = 0;
      const slideCount = slides.length;
      
      function showNextSlide() {
        slides[currentSlide].classList.remove('active');
        currentSlide = (currentSlide + 1) % slideCount;
        slides[currentSlide].classList.add('active');
      }
      
      // Change slide every 5 seconds
      setInterval(showNextSlide, 5000);
      
      // Initialize first slide
      slides[0].classList.add('active');
    });
  </script>
</body>
</html>