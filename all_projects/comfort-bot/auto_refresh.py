import time
import random
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.action_chains import ActionChains
from selenium.common.exceptions import TimeoutException, NoSuchElementException

# Configuration
LOGIN_URL = "http://192.168.100.38:8080/dvr/"
TARGET_URL = "http://192.168.100.38:8080/dvr/site_details2.php?type=dvr_offline"
USERNAME = "aniruddh"
PASSWORD = "root"
MIN_DELAY = 20  # Minimum seconds between clicks
MAX_DELAY = 25  # Maximum seconds between clicks
REFRESH_TIMEOUT = 60  # Max seconds to wait for refresh to complete

def wait_for_refresh_complete(driver, button, original_text="Refresh"):
    """Wait until button text returns to original state"""
    try:
        WebDriverWait(driver, REFRESH_TIMEOUT).until(
            lambda d: button.text == original_text
        )
        return True
    except TimeoutException:
        print(f"Timeout waiting for refresh to complete (button text: {button.text})")
        return False

def safe_click(driver, element):
    """Click element with proper waiting and scrolling"""
    try:
        # Scroll to element
        driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", element)
        time.sleep(0.5)
        
        # Wait until clickable
        WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable(element)
        )
        
        # Click using ActionChains
        ActionChains(driver).move_to_element(element).pause(0.5).click().perform()
        return True
    except Exception as e:
        print(f"Click failed: {str(e)}")
        return False

def main():
    # Initialize WebDriver
    options = webdriver.ChromeOptions()
    options.add_argument("--disable-blink-features=AutomationControlled")
    options.add_experimental_option("excludeSwitches", ["enable-automation"])
    driver = webdriver.Chrome(options=options)
    driver.maximize_window()

    try:
        # Step 1: Login
        print("Logging in...")
        driver.get(LOGIN_URL)
        
        # Fill login form
        WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.NAME, "username"))
        ).send_keys(USERNAME)
        
        driver.find_element(By.NAME, "password").send_keys(PASSWORD)
        
        # Click login button
        login_button = WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable((By.CSS_SELECTOR, "input[type='submit'], button[type='submit']"))
        )
        login_button.click()
        
        # Wait for redirect to target page
        print("Waiting for redirect...")
        try:
            WebDriverWait(driver, 15).until(
                lambda d: "site_details2.php" in d.current_url
            )
        except TimeoutException:
            print("Not redirected automatically, navigating directly...")
            driver.get(TARGET_URL)
        
        # Step 2: Wait for table to load
        print("Loading data table...")
        WebDriverWait(driver, 20).until(
            EC.presence_of_element_located((By.ID, "atmTable"))
        )
        
        # Get all refresh buttons
        refresh_buttons = WebDriverWait(driver, 10).until(
            EC.presence_of_all_elements_located((By.CLASS_NAME, "refresh_btn"))
        )
        print(f"Found {len(refresh_buttons)} devices to refresh")
        
        # Step 3: Process each button
        for i, button in enumerate(refresh_buttons, 1):
            try:
                print(f"\n--- Processing device {i}/{len(refresh_buttons)} ---")
                
                # Get current button text
                original_text = button.text
                
                # Click the button
                if not safe_click(driver, button):
                    print("Standard click failed, trying JavaScript click...")
                    driver.execute_script("arguments[0].click();", button)
                
                # Wait for button text to change to "Refreshing"
                try:
                    WebDriverWait(driver, 5).until(
                        lambda d: "Refreshing" in button.text
                    )
                    print("Refresh initiated...")
                except TimeoutException:
                    print("Button text didn't change to 'Refreshing'")
                
                # Wait for refresh to complete
                if not wait_for_refresh_complete(driver, button, original_text):
                    print("Refresh may not have completed properly")
                else:
                    print("Refresh completed successfully")
                
                # Wait random delay before next click
                if i < len(refresh_buttons):
                    delay = random.randint(MIN_DELAY, MAX_DELAY)
                    print(f"Waiting {delay} seconds before next device...")
                    time.sleep(delay)
                
            except Exception as e:
                print(f"Error processing device {i}: {str(e)}")
                continue
                
        print("\nAll devices processed successfully!")
        
    except Exception as e:
        print(f"\nFatal error: {str(e)}")
    finally:
        driver.quit()

if __name__ == "__main__":
    main()