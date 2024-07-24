import sys
import json
import face_recognition

def compare_faces(stored_face_path, live_face_path):
    try:
        stored_image = face_recognition.load_image_file(stored_face_path)
        live_image = face_recognition.load_image_file(live_face_path)

        stored_encodings = face_recognition.face_encodings(stored_image)
        live_encodings = face_recognition.face_encodings(live_image)

        if len(stored_encodings) == 0 or len(live_encodings) == 0:
            return {'success': False, 'message': 'No face detected in one or both images.'}

        stored_encoding = stored_encodings[0]
        live_encoding = live_encodings[0]

        distance = face_recognition.face_distance([stored_encoding], live_encoding)[0]
        match_percentage = (1 - distance) * 100

        results = face_recognition.compare_faces([stored_encoding], live_encoding)

        if results[0]:
            return {'success': True, 'message': 'Faces match.', 'match_percentage': match_percentage}
        else:
            return {'success': False, 'message': 'Faces do not match.', 'match_percentage': match_percentage}
    except Exception as e:
        return {'success': False, 'message': str(e)}

if __name__ == "__main__":
    stored_face_path = sys.argv[1]
    live_face_path = sys.argv[2]
    result = compare_faces(stored_face_path, live_face_path)
    print(json.dumps(result))
