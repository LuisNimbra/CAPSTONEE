"""
PESO DSS — Flask ML API
Endpoints:
  GET  /status   — model health check & metadata
  POST /train    — train/retrain ML model
  POST /predict  — rank applicants for a job vacancy
"""
import os
import json
import joblib
import numpy as np
from flask import Flask, request, jsonify

from preprocess import build_feature_vector
from train_model import train, MODEL_PATH, META_PATH

app = Flask(__name__)

# ── Helpers ──────────────────────────────────────────────────
def _load_model():
    if not os.path.exists(MODEL_PATH):
        return None, None, None
    bundle = joblib.load(MODEL_PATH)
    return bundle['clf'], bundle['le'], bundle['meta']


# ── /status ──────────────────────────────────────────────────
@app.get('/status')
def status():
    clf, le, meta = _load_model()
    if clf is None:
        return jsonify({'status': 'no_model', 'message': 'Model not trained yet.'})
    return jsonify({'status': 'ok', **meta})


# ── /train ───────────────────────────────────────────────────
@app.post('/train')
def train_endpoint():
    db_conf = request.get_json(silent=True) or {}
    result  = train(db_conf if db_conf else None)
    status_code = 200 if result.get('success') else 500
    return jsonify(result), status_code


# ── /predict ─────────────────────────────────────────────────
@app.post('/predict')
def predict():
    data       = request.get_json(silent=True) or {}
    applicants = data.get('applicants', [])

    if not applicants:
        return jsonify({'error': 'No applicants provided'}), 400

    clf, le, meta = _load_model()

    if clf is None:
        # Fallback: weighted score without model
        predictions = []
        for a in applicants:
            score = (
                0.20 * (float(a.get('education_encoded', 2)) / 5) +
                0.30 * min(float(a.get('years_experience', 0)) / max(float(a.get('required_experience', 1)), 1), 1.0) +
                0.50 * float(a.get('skill_match_score', 0))
            )
            predictions.append({'applicant_id': a['applicant_id'], 'score': round(score, 4)})
        predictions.sort(key=lambda x: x['score'], reverse=True)
        return jsonify({
            'model':       'Weighted Score (fallback)',
            'accuracy':    0.0,
            'predictions': predictions,
        })

    # Build feature matrix
    X = np.array([build_feature_vector(a) for a in applicants])

    # Predict probability (use predict_proba if available, else decision function)
    if hasattr(clf, 'predict_proba'):
        proba = clf.predict_proba(X)          # shape: (n_samples, n_classes)
        scores = proba.max(axis=1)            # confidence of best class
    else:
        scores = np.ones(len(X)) * 0.5

    # Normalize scores to [0,1]
    if scores.max() > 0:
        scores = scores / scores.max()

    predictions = [
        {'applicant_id': a['applicant_id'], 'score': round(float(s), 4)}
        for a, s in zip(applicants, scores)
    ]
    predictions.sort(key=lambda x: x['score'], reverse=True)

    return jsonify({
        'model':       meta.get('model', 'Unknown'),
        'accuracy':    meta.get('accuracy', 0.0),
        'precision':   meta.get('precision', 0.0),
        'recall':      meta.get('recall', 0.0),
        'f1':          meta.get('f1', 0.0),
        'predictions': predictions,
    })


if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    print(f"PESO DSS ML API starting on port {port}")
    print("Endpoints: GET /status  |  POST /train  |  POST /predict")
    app.run(host='0.0.0.0', port=port, debug=False)
