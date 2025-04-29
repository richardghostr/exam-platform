document.addEventListener("DOMContentLoaded", () => {
    // Mobile Menu Toggle
    const mobileMenuToggle = document.querySelector(".mobile-menu-toggle")
    const header = document.querySelector(".header")
  
    if (mobileMenuToggle) {
      mobileMenuToggle.addEventListener("click", () => {
        header.classList.toggle("mobile-menu-active")
      })
    }
  
    // Smooth Scrolling for Anchor Links
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
      anchor.addEventListener("click", function (e) {
        e.preventDefault()
  
        const targetId = this.getAttribute("href")
        if (targetId === "#") return
  
        const targetElement = document.querySelector(targetId)
        if (targetElement) {
          // Close mobile menu if open
          if (header.classList.contains("mobile-menu-active")) {
            header.classList.remove("mobile-menu-active")
          }
  
          // Scroll to target
          window.scrollTo({
            top: targetElement.offsetTop - 80, // Adjust for header height
            behavior: "smooth",
          })
        }
      })
    })
  
    // Testimonials Slider (Simple Version)
    const testimonials = document.querySelectorAll(".testimonial")
    let currentTestimonial = 0
  
    function showTestimonial(index) {
      testimonials.forEach((testimonial, i) => {
        if (i === index) {
          testimonial.style.display = "block"
        } else {
          testimonial.style.display = "none"
        }
      })
    }
  
    // Only initialize if we're on mobile and have testimonials
    function initTestimonialSlider() {
      if (window.innerWidth <= 768 && testimonials.length > 1) {
        // Initially show only the first testimonial on mobile
        showTestimonial(currentTestimonial)
  
        // Set interval to rotate testimonials
        setInterval(() => {
          currentTestimonial = (currentTestimonial + 1) % testimonials.length
          showTestimonial(currentTestimonial)
        }, 5000)
      } else {
        // On desktop show all testimonials
        testimonials.forEach((testimonial) => {
          testimonial.style.display = "block"
        })
      }
    }
  
    // Initialize testimonial slider and update on window resize
    if (testimonials.length) {
      initTestimonialSlider()
      window.addEventListener("resize", initTestimonialSlider)
    }
  
    // Sticky Header
    const heroSection = document.querySelector(".hero")
  
    function updateHeaderOnScroll() {
      if (window.scrollY > 50) {
        header.classList.add("header-scrolled")
      } else {
        header.classList.remove("header-scrolled")
      }
    }
  
    if (heroSection) {
      window.addEventListener("scroll", updateHeaderOnScroll)
      updateHeaderOnScroll() // Initial check
    }
  
    // FAQ Accordion (if present)
    const faqItems = document.querySelectorAll(".faq-item")
  
    faqItems.forEach((item) => {
      const question = item.querySelector(".faq-question")
  
      if (question) {
        question.addEventListener("click", () => {
          // Close all other items
          faqItems.forEach((otherItem) => {
            if (otherItem !== item && otherItem.classList.contains("active")) {
              otherItem.classList.remove("active")
            }
          })
  
          // Toggle current item
          item.classList.toggle("active")
        })
      }
    })
  })
  