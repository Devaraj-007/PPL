// Form submission for main contact form
document.getElementById('mainContactForm').addEventListener('submit', function (e) {
    e.preventDefault();
    submitContactForm(this, 'main');
});

// Form submission for popup contact form
function submitPopupForm(e) {
    e.preventDefault();
    submitContactForm(document.getElementById('popupContactForm'), 'popup');
}

function submitContactForm(form, formType) {
    console.log('Form submission started for:', formType);
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
    submitBtn.disabled = true;

    // Hide previous messages
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    if (successMessage) successMessage.style.display = 'none';
    if (errorMessage) errorMessage.style.display = 'none';

    // Prepare form data
    const formData = new FormData();
    
    if (formType === 'main') {
        formData.append('name', document.getElementById('name').value);
        formData.append('email', document.getElementById('email').value);
        formData.append('phone', document.getElementById('phone').value);
        formData.append('service', document.getElementById('service').value);
        formData.append('message', document.getElementById('message').value);
        formData.append('form_type', 'main_contact');
    } else {
        formData.append('name', document.getElementById('popupName').value);
        formData.append('email', document.getElementById('popupEmail').value);
        formData.append('phone', document.getElementById('popupPhone').value);
        formData.append('message', document.getElementById('popupMessage').value);
        formData.append('form_type', 'popup_contact');
    }

    // Log form data for debugging
    console.log('FormData contents:');
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }

    // First test with the simple PHP file
    const testUrl = 'process_enquiry.php'; // Change back to 'process_contact.php' after testing
    
    console.log('Sending request to:', testUrl);
    
    // Send data to PHP file
    fetch(testUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);
        return response.text().then(text => {
            console.log('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
            }
        });
    })
    .then(data => {
        console.log('Parsed response data:', data);
        if (data.success) {
            // Show success message
            const successElement = document.getElementById('successMessage');
            if (successElement) {
                successElement.style.display = 'block';
                successElement.innerHTML = `
                    <h4><i class="fas fa-check-circle me-2"></i> Thank you!</h4>
                    <p class="mb-0">${data.message}</p>
                `;
            }
            
            form.reset();
            // Scroll to success message for main form
            if (formType === 'main' && successElement) {
                successElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        } else {
            throw new Error(data.message || 'Unknown error occurred');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        const errorElement = document.getElementById('errorMessage');
        if (errorElement) {
            errorElement.style.display = 'block';
            const errorText = document.getElementById('errorText');
            if (errorText) {
                errorText.textContent = error.message || 'There was a problem submitting your enquiry. Please try again.';
            }
        }
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        console.log('Form submission completed');
    });
}

// Popup form functions
function openForm() {
    document.getElementById('popupForm').style.display = 'flex';
}

function closeForm() {
    document.getElementById('popupForm').style.display = 'none';
}
