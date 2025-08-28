from selenium import webdriver
from selenium.webdriver.common.by import By
import time

options = webdriver.ChromeOptions()
options.add_argument("--start-maximized")
driver = webdriver.Chrome(options=options)

try:
    # Step 1: Go to login page
    driver.get("http://192.168.100.38:8080/dvr/login.php")
    time.sleep(2)

    # Step 2: Fill in login details
    driver.find_element(By.ID, "username").send_keys("aniruddh")
    driver.find_element(By.ID, "password").send_keys("root")

    # Step 3: Click the correct login button
    driver.find_element(By.CSS_SELECTOR, 'button[type="submit"]').click()

    # Step 4: Navigate to site list
    time.sleep(3)
    driver.get("http://192.168.100.38:8080/dvr/site_details2.php?type=all_sites")
    time.sleep(5)

    # Step 5: Process ATM table
    table = driver.find_element(By.ID, "atmTable")
    rows = table.find_elements(By.TAG_NAME, "tr")[1:]  # skip header row

    for index, row in enumerate(rows):
        try:
            refresh_btn = row.find_element(By.CSS_SELECTOR, "td:nth-child(5) a.refresh_btn")
            print(f"Refreshing ATM {index+1}: {refresh_btn.get_attribute('data-atmid')}")
            refresh_btn.click()
            time.sleep(20 + index % 10)  # staggered wait: 20–30s
        except Exception as e:
            print(f"Failed on row {index+1}: {e}")

finally:
    driver.quit()
