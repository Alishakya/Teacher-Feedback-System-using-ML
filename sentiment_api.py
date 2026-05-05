from fastapi import FastAPI
from pydantic import BaseModel
import pickle

# Load trained model
with open("logistic_regression_model.pkl", "rb") as f:
    model = pickle.load(f)

with open("tfidf_vectorizer.pkl", "rb") as f:
    vectorizer = pickle.load(f)

app = FastAPI(title="Student Feedback Analyzer")

# Request schema
class Feedback(BaseModel):
    text: str

# Label mapping
LABELS = {
    0: "negative",
    1: "neutral",
    2: "positive"
}

@app.post("/classify")
def analyze_feedback(data: Feedback):
    X = vectorizer.transform([data.text])
    prediction = model.predict(X)[0]

    sentiment = LABELS.get(prediction, "neutral")

    return {"sentiment": sentiment}