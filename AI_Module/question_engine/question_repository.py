import json
import mysql.connector

from config import DB_HOST, DB_USER, DB_PASSWORD, DB_NAME


# ------------------------------------------------------------
# Database Connection
# ------------------------------------------------------------

def get_db_connection():
    """
    Creates and returns a MySQL database connection.
    """

    return mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="lost_connect_db"
    )


# ------------------------------------------------------------
# Get Saved Question Set
# ------------------------------------------------------------

def get_saved_questions(category: str, report_type: str):
    """
    Retrieves a saved question set for a specific
    category and report type.

    Returns:
        dict  -> question set found
        None  -> question set not found
    """

    connection = None
    cursor = None

    try:
        connection = get_db_connection()
        cursor = connection.cursor(dictionary=True)

        query = """
            SELECT
                category,
                report_type,
                version,
                questions_json
            FROM question_sets
            WHERE category = %s
              AND report_type = %s
            LIMIT 1
        """

        cursor.execute(
            query,
            (category, report_type)
        )

        row = cursor.fetchone()

        if row is None:
            return None

        questions = json.loads(row["questions_json"])

        return {
            "category": row["category"],
            "report_type": row["report_type"],
            "version": row["version"],
            "questions": questions
        }

    except Exception as e:
        raise Exception(
            f"Failed to retrieve question set: {str(e)}"
        )

    finally:

        if cursor:
            cursor.close()

        if connection:
            connection.close()


# ------------------------------------------------------------
# Save Question Set
# ------------------------------------------------------------

def save_question_set(
    category: str,
    report_type: str,
    question_set: dict
):
    """
    Saves a newly generated question set into MySQL.
    """

    connection = None
    cursor = None

    try:
        connection = get_db_connection()
        cursor = connection.cursor()

        version = question_set.get("version", 1)

        questions = question_set.get(
            "questions",
            []
        )

        questions_json = json.dumps(
            questions,
            ensure_ascii=False
        )

        query = """
            INSERT INTO question_sets
            (
                category,
                report_type,
                version,
                questions_json
            )
            VALUES (%s, %s, %s, %s)
        """

        cursor.execute(
            query,
            (
                category,
                report_type,
                version,
                questions_json
            )
        )

        connection.commit()

    except Exception as e:

        if connection:
            connection.rollback()

        raise Exception(
            f"Failed to save question set: {str(e)}"
        )

    finally:

        if cursor:
            cursor.close()

        if connection:
            connection.close()