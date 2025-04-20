document.addEventListener('DOMContentLoaded', function() {
    // Initialize Stripe
    const stripe = Stripe('<?php echo $stripePublishableKey; ?>');
    const elements = stripe.elements();
    
    // Create card Element
    const cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#32325d',
                fontFamily: '"Segoe UI", Tahoma, Geneva, Verdana, sans-serif',
                '::placeholder': {
                    color: '#aab7c4'
                }
            },
            invalid: {
                color: '#fa755a',
                iconColor: '#fa755a'
            }
        }
    });
    
    cardElement.mount('#card-element');
    
    // Set default card values after mounting
    setTimeout(() => {
        cardElement.update({
            value: {
                postalCode: '11111'
            }
        });
        
        const iframe = document.querySelector('iframe.StripeElement');
        if (iframe) {
            const simulateInput = () => {
                cardElement._implementation._frame._emit('autofill', {
                    cardNumber: '4242424242424242',
                    expMonth: '12',
                    expYear: '26',
                    cvc: '123'
                });
            };
            simulateInput();
        }
    }, 1000);
    
    // Handle form submission
    const form = document.getElementById('payment-form');
    const cardButton = document.getElementById('submit-button');
    const cardErrors = document.getElementById('card-errors');
    const successPopup = document.getElementById('success-popup');
    const popupCloseButton = document.getElementById('popup-close');
    
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        
        cardButton.disabled = true;
        cardButton.innerHTML = 'Processing...';
        
        try {
            const {paymentMethod, error} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
                billing_details: {
                    name: document.getElementById('card_name').value
                }
            });
            
            if (error) {
                cardErrors.textContent = error.message;
                cardButton.disabled = false;
                cardButton.innerHTML = 'Pay Now ₹<?php echo number_format($total, 2); ?>';
            } else {
                successPopup.classList.add('show-popup');
                document.getElementById('stripeToken').value = paymentMethod.id;
            }
        } catch (e) {
            cardErrors.textContent = "An unexpected error occurred. Please try again.";
            cardButton.disabled = false;
            cardButton.innerHTML = 'Pay Now ₹<?php echo number_format($total, 2); ?>';
        }
        ajax
    });
    
    popupCloseButton.addEventListener('click', function() {
        successPopup.classList.remove('show-popup');
        form.submit();
    });
});