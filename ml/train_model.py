"""
Model training script — run standalone or called by Flask /train endpoint.
Trains Naïve Bayes, Decision Tree, and KNN.
Selects best via 10-Fold Cross Validation on accuracy.
Saves best model to models/best_model.pkl with metadata.
"""
import os
import json
import joblib
import numpy as np
import pymysql

from sklearn.naive_bayes import GaussianNB
from sklearn.tree import DecisionTreeClassifier
from sklearn.neighbors import KNeighborsClassifier
from sklearn.model_selection import cross_val_score, StratifiedKFold
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score
from sklearn.preprocessing import LabelEncoder

from preprocess import build_feature_vector, encode_education, EDUCATION_MAP

MODEL_DIR  = os.path.join(os.path.dirname(__file__), 'models')
MODEL_PATH = os.path.join(MODEL_DIR, 'best_model.pkl')
META_PATH  = os.path.join(MODEL_DIR, 'model_meta.json')

CANDIDATES = {
    'Naive Bayes':    GaussianNB(),
    'Decision Tree':  DecisionTreeClassifier(max_depth=6, random_state=42),
    'KNN':            KNeighborsClassifier(n_neighbors=5),
}


def _fetch_training_data(db_conf: dict):
    """
    Builds training samples from historical placements.
    Target variable: placed_job_category (job title bucket).
    Features: 7 applicant profile features.
    """
    conn = pymysql.connect(
        host=db_conf.get('db_host', 'localhost'),
        user=db_conf.get('db_user', 'root'),
        password=db_conf.get('db_pass', ''),
        database=db_conf.get('db_name', 'peso_dss'),
        charset='utf8mb4',
    )
    cur = conn.cursor(pymysql.cursors.DictCursor)

    # Fetch placements with applicant profile and job details
    cur.execute("""
        SELECT
            a.education_level, a.years_experience, a.age,
            a.id AS applicant_id,
            jv.required_education, jv.required_experience, jv.job_title,
            p.position
        FROM placements p
        JOIN applicants a ON a.id = p.applicant_id
        JOIN job_vacancies jv ON jv.id = p.job_id
    """)
    placements = cur.fetchall()

    # Fetch skill counts and match scores per applicant/job pair
    cur.execute("SELECT applicant_id, skill FROM applicant_skills")
    app_skills_raw = cur.fetchall()
    app_skills = {}
    for row in app_skills_raw:
        app_skills.setdefault(row['applicant_id'], []).append(row['skill'].lower())

    cur.execute("SELECT job_id, skill FROM job_required_skills")
    job_skills_raw = cur.fetchall()
    job_skills = {}
    for row in job_skills_raw:
        job_skills.setdefault(row['job_id'], []).append(row['skill'].lower())

    conn.close()

    X, y = [], []
    for p in placements:
        edu_rank     = encode_education(p['education_level'])
        req_edu_rank = encode_education(p['required_education'] or 'High School')
        a_skills     = app_skills.get(p['applicant_id'], [])
        j_skills     = job_skills.get(p.get('job_id', 0), [])
        matched      = len(set(a_skills) & set(j_skills))
        skill_score  = matched / len(j_skills) if j_skills else 0.0

        features = {
            'education_encoded': edu_rank,
            'edu_meets_req':     1 if edu_rank >= req_edu_rank else 0,
            'years_experience':  float(p['years_experience'] or 0),
            'exp_meets_req':     1 if float(p['years_experience'] or 0) >= float(p['required_experience'] or 0) else 0,
            'skill_match_score': skill_score,
            'skill_count':       len(a_skills),
            'age':               int(p['age'] or 25),
        }
        X.append(build_feature_vector(features))
        y.append(p['position'] or p['job_title'])

    return np.array(X), np.array(y)


def _augment_data(X: np.ndarray, y: np.ndarray, target_per_class: int = 10):
    """Adds synthetic samples if real data is small (jitter-based oversampling)."""
    rng = np.random.default_rng(42)
    classes, counts = np.unique(y, return_counts=True)
    X_aug, y_aug = list(X), list(y)
    for cls, cnt in zip(classes, counts):
        needed = max(0, target_per_class - cnt)
        idxs   = np.where(y == cls)[0]
        for _ in range(needed):
            base  = X[rng.choice(idxs)]
            noise = rng.normal(0, 0.05, base.shape)
            X_aug.append(np.clip(base + noise, 0, None))
            y_aug.append(cls)
    return np.array(X_aug), np.array(y_aug)


def train(db_conf: dict | None = None) -> dict:
    os.makedirs(MODEL_DIR, exist_ok=True)

    if db_conf:
        try:
            X, y = _fetch_training_data(db_conf)
        except Exception as e:
            return {'success': False, 'error': str(e)}
    else:
        # Synthetic data for cold-start testing
        rng = np.random.default_rng(0)
        labels = [
            'Customer Service Representative', 'Administrative Assistant',
            'Software Developer', 'Sales Associate', 'Accountant',
        ]
        X, y = [], []
        for i, lbl in enumerate(labels):
            for _ in range(20):
                X.append([
                    rng.integers(2, 6),
                    rng.integers(0, 2),
                    float(rng.integers(0, 10)),
                    rng.integers(0, 2),
                    float(rng.uniform(0, 1)),
                    rng.integers(1, 10),
                    rng.integers(18, 55),
                ])
                y.append(lbl)
        X, y = np.array(X), np.array(y)

    if len(X) < 5:
        X, y = _augment_data(X, y)

    le = LabelEncoder()
    y_enc = le.fit_transform(y)

    cv = StratifiedKFold(n_splits=min(10, len(np.unique(y_enc))), shuffle=True, random_state=42)
    results = {}
    for name, clf in CANDIDATES.items():
        try:
            scores = cross_val_score(clf, X, y_enc, cv=cv, scoring='accuracy')
            results[name] = float(scores.mean())
        except Exception:
            results[name] = 0.0

    best_name = max(results, key=results.get)
    best_clf  = CANDIDATES[best_name]
    best_clf.fit(X, y_enc)

    y_pred = best_clf.predict(X)
    meta = {
        'model':     best_name,
        'accuracy':  float(accuracy_score(y_enc, y_pred)),
        'precision': float(precision_score(y_enc, y_pred, average='weighted', zero_division=0)),
        'recall':    float(recall_score(y_enc, y_pred, average='weighted', zero_division=0)),
        'f1':        float(f1_score(y_enc, y_pred, average='weighted', zero_division=0)),
        'cv_results': results,
        'classes':   list(le.classes_),
    }

    joblib.dump({'clf': best_clf, 'le': le, 'meta': meta}, MODEL_PATH)
    with open(META_PATH, 'w') as f:
        json.dump(meta, f, indent=2)

    return {'success': True, **meta}


if __name__ == '__main__':
    result = train()
    print(json.dumps(result, indent=2))
