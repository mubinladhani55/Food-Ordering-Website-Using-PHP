// Use a shared cart key for all restaurant menus
const SHARED_CART_KEY = 'sharedRestaurantCart';
let cart = [];
const TAX_RATE = 0.10;
let currentOrderId = null;
const RESTAURANT_TYPE = restaurantDbValue; // Use PHP-provided restaurant database value

// Load cart data on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load shared cart from localStorage
    const savedCart = localStorage.getItem(SHARED_CART_KEY);
    if (savedCart) {
        cart = JSON.parse(savedCart);
    }
    
    // Load the order ID from localStorage or create a new one
    currentOrderId = localStorage.getItem('currentOrderId') || generateOrderId();
    localStorage.setItem('currentOrderId', currentOrderId);
    
    setupCartModal();
    updateCartBadge();
    
    // Category Selection
    document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('click', () => {
            const categoryId = card.dataset.categoryId;
            
            // Update active class
            document.querySelectorAll('.category-card').forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            
            // Fetch menu items for selected category
            const formData = new FormData();
            formData.append('action', 'get_menu_items');
            formData.append('category_id', categoryId);
            
            fetch('chinese_menu.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update URL without page reload
                    const url = new URL(window.location);
                    url.searchParams.set('category', categoryId);
                    window.history.pushState({}, '', url);
                    
                    // Update menu items display
                    window.menuData = data.data;
                    displayMenuItems(data.data);
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            filterMenuItems(searchTerm);
        });
    }
    
    // Initial display of menu items
    displayMenuItems(menuData);
    
    // Set the order ID in the form
    const orderIdInput = document.getElementById('orderIdInput');
    if (orderIdInput) {
        orderIdInput.value = currentOrderId;
    }
});

function setupCartModal() {
    const cartButton = document.getElementById('cartButton');
    const cartModal = document.getElementById('cartModal');
    const closeModal = document.getElementById('closeModal');
    
    if (cartButton) {
        cartButton.addEventListener('click', function() {
            updateCartDisplay();
            if (cartModal) cartModal.style.display = 'block';
        });
    }
    
    if (closeModal) {
        closeModal.addEventListener('click', function() {
            if (cartModal) cartModal.style.display = 'none';
        });
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (cartModal && event.target === cartModal) {
            cartModal.style.display = 'none';
        }
    });
    
    // Update form submission handler
    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
        orderForm.addEventListener('submit', function(event) {
            if (cart.length === 0) {
                event.preventDefault();
                alert('Your cart is empty!');
                return false;
            }
            
            // Ensure the order ID is set
            const orderIdInput = document.getElementById('orderIdInput');
            if (orderIdInput) {
                orderIdInput.value = currentOrderId;
            }
        });
    }
}

function generateOrderId() {
    const timestamp = new Date().getTime();
    const random = Math.floor(Math.random() * 10000);
    return `order_${timestamp}_${random}`;
}

function filterMenuItems(searchTerm) {
    const filteredItems = menuData.filter(item => 
        item.name.toLowerCase().includes(searchTerm) ||
        item.description.toLowerCase().includes(searchTerm)
    );
    displayMenuItems(filteredItems);
}

function getSpicyLevel(level) {
    return '🌶️'.repeat(level);
}

function findMenuItemById(itemId) {
    const id = parseInt(itemId);
    const item = menuData.find(i => parseInt(i.id) === id);
    if (item) return item;
    
    const cartItem = cart.find(i => i.uniqueId === `chinese_${id}`);
    if (cartItem) {
        return {
            id: cartItem.id,
            name: cartItem.name,
            price: cartItem.price,
            image_path: cartItem.image
        };
    }
    
    return null;
}

function updateCart(restaurantPrefix, itemId, change) {
    itemId = parseInt(itemId);
    const cartItemId = `${restaurantPrefix}_${itemId}`;
    const cartItem = cart.find(i => i.uniqueId === cartItemId);
    
    if (cartItem) {
        cartItem.quantity += change;
        
        if (cartItem.quantity <= 0) {
            cart = cart.filter(i => i.uniqueId !== cartItemId);
        }
    } else if (change > 0) {
        const item = findMenuItemById(itemId);
        if (!item) {
            console.error("Item not found:", itemId);
            return false;
        }
        
        cart.push({
            uniqueId: cartItemId,
            id: itemId,
            name: item.name,
            price: parseFloat(item.price),
            image: item.image_path,
            quantity: 1,
            restaurant: RESTAURANT_TYPE
        });
    }
    
    localStorage.setItem(SHARED_CART_KEY, JSON.stringify(cart));
    saveOrderToDatabase(itemId, change);
    updateCartDisplay();
    updateCartBadge();
    displayMenuItems(window.menuData || menuData);
    
    return true;
}

function saveOrderToDatabase(itemId, change) {
    itemId = parseInt(itemId);
    const item = findMenuItemById(itemId);
    if (!item) {
        console.error("Item not found:", itemId);
        return false;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_cart_item');
    formData.append('order_id', currentOrderId);
    formData.append('restaurant', RESTAURANT_TYPE);
    formData.append('item_id', itemId);
    formData.append('item_name', item.name);
    formData.append('quantity', change);
    formData.append('price', item.price);
    
    fetch('chinese_menu.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.order_id) {
            currentOrderId = data.order_id;
            localStorage.setItem('currentOrderId', currentOrderId);
            
            // Update the order ID in the form if it exists
            const orderIdInput = document.getElementById('orderIdInput');
            if (orderIdInput) {
                orderIdInput.value = currentOrderId;
            }
        } else {
            console.error('Failed to save order:', data.message);
            alert('Failed to update cart. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error saving order:', error);
        alert('An error occurred while updating the cart.');
    });
}

function updateCartBadge() {
    const cartBadge = document.getElementById('cartBadge');
    if (!cartBadge) return;
    
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    cartBadge.textContent = totalItems;
    cartBadge.style.display = totalItems === 0 ? 'none' : 'flex';
}

function updateCartDisplay() {
    const modalCartItems = document.getElementById('modalCartItems');
    if (!modalCartItems) return;
    
    if (cart.length === 0) {
        modalCartItems.innerHTML = '<div class="empty-cart-message">Your cart is empty</div>';
        
        document.getElementById('modalSubtotal').textContent = 'Rs0.00';
        document.getElementById('modalTax').textContent = 'Rs0.00';
        document.getElementById('modalTotal').textContent = 'Rs0.00';
        return;
    }
    
    modalCartItems.innerHTML = cart.map(item => {
        const [restaurantPrefix, itemId] = item.uniqueId.split('_');
        
        return `
            <div class="cart-item">
                <img src="${item.image}" alt="${item.name}">
                <div class="cart-item-details">
                    <h4>${item.name}</h4>
                    <p class="restaurant-tag">${item.restaurant}</p>
                    <p>Rs${parseFloat(item.price).toFixed(2)} x ${item.quantity}</p>
                </div>
                <div class="cart-item-actions">
                    <button class="quantity-btn minus" onclick="updateCart('${restaurantPrefix}', ${itemId}, -1)">-</button>
                    <span class="quantity">${item.quantity}</span>
                    <button class="quantity-btn plus" onclick="updateCart('${restaurantPrefix}', ${itemId}, 1)">+</button>
                </div>
            </div>
        `;
    }).join('');

    const subtotal = cart.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
    const tax = subtotal * TAX_RATE;
    const total = subtotal + tax;

    document.getElementById('modalSubtotal').textContent = `Rs${subtotal.toFixed(2)}`;
    document.getElementById('modalTax').textContent = `Rs${tax.toFixed(2)}`;
    document.getElementById('modalTotal').textContent = `Rs${total.toFixed(2)}`;
    
    // Update the order ID in the form
    const orderIdInput = document.getElementById('orderIdInput');
    if (orderIdInput) {
        orderIdInput.value = currentOrderId;
    }
}

function displayMenuItems(items) {
    const menuGrid = document.getElementById('menuGrid');
    if (!menuGrid) return;
    
    if (items.length === 0) {
        menuGrid.innerHTML = '<div class="no-items">No items found</div>';
        return;
    }
    
    menuGrid.innerHTML = items.map(item => {
        const itemId = parseInt(item.id);
        const cartItemId = `chinese_${itemId}`;
        const cartItem = cart.find(i => i.uniqueId === cartItemId);
        const quantity = cartItem ? cartItem.quantity : 0;
        
        return `
            <div class="menu-item">
                <img src="${item.image_path}" alt="${item.name}">
                <div class="item-details">
                    <h3>${item.name}</h3>
                    ${item.spicy_level ? `<div class="spicy-level">${getSpicyLevel(item.spicy_level)}</div>` : ''}
                    <p>${item.description}</p>
                    <div class="price">Rs${parseFloat(item.price).toFixed(2)}</div>
                    <div class="quantity-control">
                        <button class="quantity-btn minus" onclick="updateCart('chinese', ${itemId}, -1)" ${quantity === 0 ? 'disabled' : ''}>-</button>
                        <input type="text" class="quantity-input" value="${quantity}" readonly>
                        <button class="quantity-btn plus" onclick="updateCart('chinese', ${itemId}, 1)">+</button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Make updateCart function globally available
window.updateCart = updateCart;