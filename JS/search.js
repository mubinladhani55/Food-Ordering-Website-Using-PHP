document.addEventListener('DOMContentLoaded', function() {
    const restaurants = [
      { name: "Work on Fire", url: "chinese_menu.php?restaurant=WorkOnFire" },
      { name: "Pizza Hub", url: "chinese_menu.php?restaurant=PizzaHub" },
      { name: "Tandoor Hut", url: "chinese_menu.php?restaurant=TandoorHut" },
      { name: "Thai Spice", url: "chinese_menu.php?restaurant=ThaiSpice" },
      { name: "Burger Station", url: "chinese_menu.php?restaurant=BurgerStation" },
      { name: "Pasta Paradise", url: "chinese_menu.php?restaurant=PastaParadise" },
      { name: "Sushi World", url: "chinese_menu.php?restaurant=SushiWorld" },
      { name: "Mexican Fiesta", url: "chinese_menu.php?restaurant=MexicanFiesta" }
    ];
      
    const searchInput = document.getElementById('restaurantSearch');
    const searchButton = document.getElementById('searchButton');
    const searchResults = document.getElementById('searchResults');
      
    // Function to display autocomplete suggestions
    function showSuggestions() {
      const query = searchInput.value.toLowerCase().trim();
      searchResults.innerHTML = '';
          
      if (query === '') {
        searchResults.style.display = 'none';
        return;
      }
          
      const matchedRestaurants = restaurants.filter(restaurant => 
        restaurant.name.toLowerCase().includes(query)
      );
          
      if (matchedRestaurants.length > 0) {
        searchResults.style.display = 'block';
        const resultsList = document.createElement('div');
        resultsList.className = 'list-group shadow-sm';
              
        matchedRestaurants.forEach(restaurant => {
          const resultItem = document.createElement('a');
          resultItem.href = restaurant.url;
          resultItem.className = 'list-group-item list-group-item-action';
          
          // Highlight the matching text
          const index = restaurant.name.toLowerCase().indexOf(query);
          if (index >= 0) {
            const beforeMatch = restaurant.name.substring(0, index);
            const match = restaurant.name.substring(index, index + query.length);
            const afterMatch = restaurant.name.substring(index + query.length);
            resultItem.innerHTML = beforeMatch + '<strong>' + match + '</strong>' + afterMatch;
          } else {
            resultItem.textContent = restaurant.name;
          }
                  
          resultsList.appendChild(resultItem);
        });
              
        searchResults.appendChild(resultsList);
      } else {
        searchResults.style.display = 'block';
        searchResults.innerHTML = '<div class="alert alert-info">No restaurants found matching your search.</div>';
      }
    }
      
    // Function to perform full search (for search button click)
    function performSearch() {
      const query = searchInput.value.toLowerCase().trim();
      
      if (query === '') {
        return;
      }
      
      // If only one restaurant matches, navigate directly to it
      const matchedRestaurants = restaurants.filter(restaurant => 
        restaurant.name.toLowerCase().includes(query)
      );
      
      if (matchedRestaurants.length === 1) {
        window.location.href = matchedRestaurants[0].url;
      } else {
        showSuggestions();
      }
    }
      
    // Event listeners
    searchInput.addEventListener('input', showSuggestions);
    
    searchButton.addEventListener('click', performSearch);
      
    searchInput.addEventListener('keyup', function(event) {
      if (event.key === 'Enter') {
        performSearch();
      }
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(event) {
      if (!searchInput.contains(event.target) && !searchResults.contains(event.target)) {
        searchResults.style.display = 'none';
      }
    });
      
    // Cart functionality
    const cartLink = document.getElementById('cartLink');
    if (cartLink) {
      cartLink.addEventListener('click', function(e) {
        e.preventDefault();
        const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
        cartModal.show();
      });
    }
  });