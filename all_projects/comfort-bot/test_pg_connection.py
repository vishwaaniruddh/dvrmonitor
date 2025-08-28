import psycopg2
from psycopg2 import OperationalError

def test_postgres_connection():
    try:
        connection = psycopg2.connect(
            host="192.168.100.23",
            port=5432,
            database="esurv",   # Change if needed
            user="postgres",
            password="root"
        )
        print("✅ Connection successful!")
        connection.close()
    except OperationalError as e:
        print("❌ Connection failed:")
        print(e)

if __name__ == "__main__":
    test_postgres_connection()
