import csv
import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import NoSuchElementException, TimeoutException

# Configuration
CSV_PATH = r'C:\Users\Aniruddh\Desktop\yn_update_product_info.csv'
WEBSITE_URL = 'https://yosshitaneha.com/wp-admin'
USERNAME = 'vishwaaniruddh@gmail.com'
PASSWORD = 'AVav@@2024'  # Replace with your actual password

# Columns in your CSV (0-based index)
PRODUCT_NAME_COL = 2  # Column C
SKU_COL = 3           # Column D
DESCRIPTION_COL = 8   # Column I

def login_to_wordpress(driver):
    """Log in to WordPress admin panel"""
    driver.get(WEBSITE_URL)
    
    # Wait for login page to load
    WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.ID, 'user_login'))
    )
    
    # Fill in login form
    username_field = driver.find_element(By.ID, 'user_login')
    username_field.send_keys(USERNAME)
    
    password_field = driver.find_element(By.ID, 'user_pass')
    password_field.send_keys(PASSWORD)
    
    # Submit form
    driver.find_element(By.ID, 'wp-submit').click()
    
    # Wait for dashboard to load
    WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.ID, 'wpadminbar'))
    )

def update_product(driver, sku, product_name, description):
    """Update product information in WooCommerce"""
    # Go to products page
    driver.get('https://yosshitaneha.com/wp-admin/edit.php?post_type=product')
    
    # Search for the product by SKU
    search_field = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.ID, 'post-search-input'))
    )
    search_field.clear()
    search_field.send_keys(sku)
    driver.find_element(By.ID, 'search-submit').click()
    
    # Wait for search results
    time.sleep(2)  # Needed for search results to load
    
    try:
        # Find and click the product link
        product_link = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.CSS_SELECTOR, 'a.row-title'))
        )
        product_link.click()
    except (NoSuchElementException, TimeoutException):
        print(f"Product with SKU {sku} not found")
        return False
    
    # Wait for product editor to load
    WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.ID, 'title'))
    )
    
    # Update product name
    title_field = driver.find_element(By.ID, 'title')
    title_field.clear()
    title_field.send_keys(product_name)
    
    # Switch to text mode for description (in case it's in visual mode)
    try:
        driver.find_element(By.ID, 'content-html').click()
    except NoSuchElementException:
        pass
    
    # Update description
    description_field = driver.find_element(By.ID, 'content')
    driver.execute_script("arguments[0].value = arguments[1];", description_field, description)
    
    # Update the product
    driver.find_element(By.ID, 'publish').click()
    
    # Wait for update to complete
    WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.CSS_SELECTOR, '#message.updated'))
    )
    
    return True

def main():
    # Initialize Chrome WebDriver
    driver = webdriver.Chrome()
    
    try:
        # Login to WordPress
        login_to_wordpress(driver)
        
        # Read CSV file
        # with open(CSV_PATH, 'r', encoding='utf-8') as csvfile:
        with open(CSV_PATH, 'r', encoding='utf-16') as csvfile:

            reader = csv.reader(csvfile)
            next(reader)  # Skip header row if exists
            
            for row in reader:
                if len(row) <= max(PRODUCT_NAME_COL, SKU_COL, DESCRIPTION_COL):
                    continue  # Skip incomplete rows
                
                sku = row[SKU_COL].strip()
                product_name = row[PRODUCT_NAME_COL].strip()
                description = row[DESCRIPTION_COL].strip()
                
                if not sku:
                    continue  # Skip rows with empty SKU
                
                print(f"Updating product with SKU: {sku}")
                success = update_product(driver, sku, product_name, description)
                
                if success:
                    print(f"Successfully updated product: {product_name}")
                else:
                    print(f"Failed to update product with SKU: {sku}")
                
                # Small delay between updates
                time.sleep(1)
                
    except Exception as e:
        print(f"An error occurred: {str(e)}")
    finally:
        driver.quit()
        print("Script completed")

if __name__ == '__main__':
    main()